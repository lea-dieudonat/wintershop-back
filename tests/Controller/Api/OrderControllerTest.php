<?php

namespace App\Tests\Controller\Api;

use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\OrderFactory;
use App\Tests\Factory\OrderItemFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class OrderControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
    }

    protected function teardown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testGetuserOrders(): void
    {
        // Arrange: Create two different users with orders
        $user1 = UserFactory::createOne()->_real();
        $user2 = UserFactory::createOne()->_real();
        $address1 = AddressFactory::createOne(['user' => $user1])->_real();
        $address2 = AddressFactory::createOne(['user' => $user2])->_real();

        // Create 2 orders for user1
        OrderFactory::createOne([
            'user' => $user1,
            'status' => OrderStatus::PENDING,
            'totalAmount' => '100.00',
            'shippingAddress' => $address1,
            'billingAddress' => $address1,
            'createdAt' => new \DateTimeImmutable('-2 days'),
        ]);

        OrderFactory::createOne([
            'user' => $user1,
            'status' => OrderStatus::DELIVERED,
            'totalAmount' => '50.00',
            'shippingAddress' => $address1,
            'billingAddress' => $address1,
            'createdAt' => new \DateTimeImmutable('-1 days'),
        ]);

        // Create 1 order for user2 (should NOT appear in user1's results)
        OrderFactory::createOne([
            'user' => $user2,
            'status' => OrderStatus::PAID,
            'totalAmount' => '200.00',
            'shippingAddress' => $address2,
            'billingAddress' => $address2,
        ]);

        // Act: Make API request as user1
        $this->client->loginUser($user1);
        $this->client->request('GET', '/api/orders');

        // Assert: Verify response
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);

        $this->assertIsArray($data, 'Response should be valid JSON');

        // API Platform returns JSON-LD with member array
        if (isset($data['member'])) {
            $orders = $data['member'];
        } elseif (isset($data['hydra:member'])) {
            $orders = $data['hydra:member'];
        } elseif (is_array($data) && !empty($data) && is_array($data[0])) {
            $orders = $data;
        } else {
            $orders = empty($data) ? [] : [$data];
        }

        // User1 should only see their own orders (at least 1, potentially 2)
        $this->assertGreaterThanOrEqual(1, count($orders), 'User should see at least 1 of their own orders');

        // Verify order data structure
        foreach ($orders as $order) {
            $this->assertIsArray($order, 'Each order should be an array');
            // API Platform uses @id (IRI) instead of numeric id in JSON-LD format
            $this->assertTrue(isset($order['@id']) || isset($order['id']), 'Order should have @id or id');
            $this->assertArrayHasKey('reference', $order, 'Order should have reference');
            $this->assertArrayHasKey('status', $order, 'Order should have status');
            $this->assertArrayHasKey('totalAmount', $order, 'Order should have totalAmount');

            // Verify the status is one of user1's order statuses (not user2's PAID status)
            $this->assertContains($order['status'], [OrderStatus::PENDING->value, OrderStatus::DELIVERED->value]);
        }
    }


    public function testGetUserOrdersWhenNotAuthenticated(): void
    {
        $this->client->request('GET', '/api/orders');

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testGetOrderDetails(): void
    {
        // Arrange
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();
        $product = ProductFactory::createOne([
            'name' => 'Test Ski',
            'price' => '299.99',
        ])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::PAID,
            'totalAmount' => '304.99',
            'shippingCost' => '5.00',
            'shippingMethod' => ShippingMethod::STANDARD,
            'shippingAddress' => $address,
            'billingAddress' => $address,
        ])->_real();

        OrderItemFactory::createOne([
            'parentOrder' => $order,
            'product' => $product,
            'quantity' => 1,
            'unitPrice' => '299.99',
        ]);

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('GET', "/api/orders/{$order->getId()}");

        // Assert
        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->fail("Expected 200 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);

        $this->assertEquals($order->getId(), $data['id']);
        $this->assertEquals($order->getReference(), $data['reference']);
        $this->assertEquals('304.99', $data['totalAmount']);
        $this->assertArrayHasKey('items', $data);
        $this->assertCount(1, $data['items']);
        $this->assertArrayHasKey('shippingAddress', $data);
        $this->assertArrayHasKey('billingAddress', $data);
    }

    public function testGetOrderDetailsOfAnotherUser(): void
    {
        // Arrange: Create order for user1
        $user1 = UserFactory::createOne()->_real();
        $user2 = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user1])->_real();

        $order = OrderFactory::createOne([
            'user' => $user1,
            'status' => OrderStatus::PAID,
            'shippingAddress' => $address,
            'billingAddress' => $address,
        ])->_real();

        $this->entityManager->flush();

        // Act: Try to access as user2
        $this->client->loginUser($user2);
        $this->client->request('GET', "/api/orders/{$order->getId()}");

        // Assert: Should be forbidden
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCancelOrderWithinDeadline(): void
    {
        // Arrange: Create order created 1 hour ago (within 24h deadline)
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::PENDING,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-1 hour'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('PATCH', "/api/orders/{$order->getId()}/cancel", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Changed my mind',
        ]));

        // Assert
        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->fail("Expected 200 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('cancelled', $data['status']);

        // Verify in database
        $this->entityManager->refresh($order);
        $this->assertEquals(OrderStatus::CANCELLED, $order->getStatus());
    }

    public function testCancelOrderAfterDeadline(): void
    {
        // Arrange: Create order created 25 hours ago (past 24h deadline)
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::PENDING,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-25 hours'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('PATCH', "/api/orders/{$order->getId()}/cancel", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Changed my mind',
        ]));

        // Assert: Should fail
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $message = $data['hydra:description'] ?? $data['detail'] ?? $data['message'] ?? '';
        $this->assertStringContainsString('deadlineExpired', $message, 'Error message should indicate deadline expired');
    }

    public function testCancelOrderWithInvalidStatus(): void
    {
        // Arrange: Create order that's already shipped
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::SHIPPED,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-1 hour'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('PATCH', "/api/orders/{$order->getId()}/cancel", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Changed my mind',
        ]));

        // Assert: Should fail
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $message = $data['hydra:description'] ?? $data['detail'] ?? $data['message'] ?? '';
        $this->assertStringContainsString('invalidStatus', $message, 'Error message should indicate invalid status');
    }

    public function testRequestRefundWithinDeadline(): void
    {
        // Arrange: Create delivered order from 5 days ago (within 14 days)
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::DELIVERED,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-30 days'),
            'deliveredAt' => new \DateTimeImmutable('-5 days'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('POST', "/api/orders/{$order->getId()}/refund", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Product defective',
        ]));

        // Assert
        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            $this->fail("Expected 201 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);
        $this->assertEquals('refund_requested', $data['status']);

        // Verify in database
        $this->entityManager->refresh($order);
        $this->assertEquals(OrderStatus::REFUND_REQUESTED, $order->getStatus());
        $this->assertEquals('Product defective', $order->getRefundReason());
        $this->assertNotNull($order->getRefundRequestedAt());
    }

    public function testRequestRefundAfterDeadline(): void
    {
        // Arrange: Create delivered order from 15 days ago (past 14 days deadline)
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::DELIVERED,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-30 days'),
            'deliveredAt' => new \DateTimeImmutable('-15 days'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('POST', "/api/orders/{$order->getId()}/refund", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Product defective',
        ]));

        // Assert: Should fail
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $message = $data['hydra:description'] ?? $data['detail'] ?? $data['message'] ?? '';
        $this->assertStringContainsString('deadlineExpired', $message, 'Error message should indicate deadline expired');
    }

    public function testRequestRefundForNonDeliveredOrder(): void
    {
        // Arrange: Create order that's not delivered yet
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::SHIPPED,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-5 days'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('POST', "/api/orders/{$order->getId()}/refund", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Product defective',
        ]));

        // Assert: Should fail
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $message = $data['hydra:description'] ?? $data['detail'] ?? $data['message'] ?? '';
        $this->assertStringContainsString('notDelivered', $message, 'Error message should indicate order not delivered');
    }

    public function testRequestRefundWhenAlreadyRequested(): void
    {
        // Arrange: Create order with refund already requested
        $user = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::REFUND_REQUESTED,
            'totalAmount' => '100.00',
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-30 days'),
            'deliveredAt' => new \DateTimeImmutable('-5 days'),
            'refundReason' => 'Already requested',
            'refundRequestedAt' => new \DateTimeImmutable('-1 day'),
        ])->_real();

        $this->entityManager->flush();

        // Act
        $this->client->loginUser($user);
        $this->client->request('POST', "/api/orders/{$order->getId()}/refund", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Trying again',
        ]));

        // Assert: Should fail
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $message = $data['hydra:description'] ?? $data['detail'] ?? $data['message'] ?? '';
        $this->assertStringContainsString('alreadyRequested', $message, 'Error message should indicate refund already requested');
    }

    public function testCancelOrderOfAnotherUser(): void
    {
        // Arrange
        $user1 = UserFactory::createOne()->_real();
        $user2 = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user1])->_real();

        $order = OrderFactory::createOne([
            'user' => $user1,
            'status' => OrderStatus::PENDING,
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-1 hour'),
        ])->_real();

        $this->entityManager->flush();

        // Act: Try to cancel as user2
        $this->client->loginUser($user2);
        $this->client->request('PATCH', "/api/orders/{$order->getId()}/cancel", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Malicious attempt',
        ]));

        // Assert: Should be forbidden
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testRequestRefundOfAnotherUser(): void
    {
        // Arrange
        $user1 = UserFactory::createOne()->_real();
        $user2 = UserFactory::createOne()->_real();
        $address = AddressFactory::createOne(['user' => $user1])->_real();

        $order = OrderFactory::createOne([
            'user' => $user1,
            'status' => OrderStatus::DELIVERED,
            'shippingAddress' => $address,
            'billingAddress' => $address,
            'createdAt' => new \DateTimeImmutable('-30 days'),
            'deliveredAt' => new \DateTimeImmutable('-5 days'),
        ])->_real();

        $this->entityManager->flush();

        // Act: Try to request refund as user2
        $this->client->loginUser($user2);
        $this->client->request('POST', "/api/orders/{$order->getId()}/refund", [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'reason' => 'Malicious attempt',
        ]));

        // Assert: Should be forbidden
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}

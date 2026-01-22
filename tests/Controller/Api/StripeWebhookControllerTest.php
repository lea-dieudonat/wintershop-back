<?php

namespace App\Tests\Controller\Api;

use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Service\CheckoutService;
use App\Tests\Factory\OrderFactory;
use App\Tests\Factory\OrderItemFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\StripeClient;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;
    private string $webhookSecret = 'whsec_test_secret';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->catchExceptions(false);
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
        $this->webhookSecret = self::getContainer()->getParameter('stripe.webhook_secret');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    public function testHandleCheckoutSessionCompletedSuccessfully(): void
    {
        // Create a test user and order
        $user = UserFactory::createOne()->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 10,
            'price' => '50.00',
        ])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::PENDING,
            'stripeSessionId' => 'cs_test_123456',
            'totalAmount' => '105.00',
            'shippingCost' => '5.00',
            'shippingMethod' => ShippingMethod::STANDARD,
            'paidAt' => null,
        ])->_real();

        OrderItemFactory::createOne([
            'parentOrder' => $order,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);

        $this->entityManager->flush();

        // mock stripeClient to avoid real api calls
        $stripeClientMock = $this->createMock(StripeClient::class);
        $checkoutSessionsMock = $this->createMock(\Stripe\Service\Checkout\SessionService::class);
        $checkoutMock = new class($checkoutSessionsMock) {
            public function __construct(public $sessions) {}
        };
        $stripeClientMock->checkout = $checkoutMock;

        $mockFullSession = new \stdClass();
        $mockFullSession->id = 'cs_test_123456';
        $mockFullSession->payment_intent = 'pi_test_789';

        $checkoutSessionsMock
            ->expects($this->once())
            ->method('retrieve')
            ->with('cs_test_123456')
            ->willReturn($mockFullSession);

        $this->client->getContainer()->set(StripeClient::class, $stripeClientMock);

        // Create a mock webhook payload
        $payload = json_encode([
            'id' => 'evt_test_webhook',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123456',
                    'payment_status' => 'paid',
                    'customer_email' => 'test@example.com',
                ],
            ],
        ]);

        $secret = self::getContainer()->getParameter('stripe.webhook_secret');
        $signature = $this->generateStripeSignature($payload, $secret);

        // Send the webhook request
        $this->client->request(
            'POST',
            '/api/stripe/webhook',
            [],
            [],
            ['HTTP_STRIPE_SIGNATURE' => $signature],
            $payload
        );

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->fail("Expected 200 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        // Assert: Check order was updated
        $this->entityManager->refresh($order);
        $this->assertEquals(OrderStatus::PAID, $order->getStatus());
        $this->assertNotNull($order->getPaidAt());
        $this->assertEquals('pi_test_789', $order->getStripePaymentIntentId());

        // Assert: Check stock was decremented
        $this->entityManager->refresh($product);
        $this->assertEquals(8, $product->getStock()); // 10 - 2
    }

    public function testHandleCheckoutSessionCompletedWithInvalidSignature(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_webhook',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_invalid',
                ],
            ],
        ]);

        // Use invalid signature
        $invalidSignature = 't=123456789,v1=invalidsignature';

        $this->client->request('POST', '/api/stripe/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $invalidSignature,
        ], $payload);

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Invalid signature', $response->getContent());
    }

    public function testHandleCheckoutSessionCompletedWithMissingSignature(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_webhook',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_missing',
                ],
            ],
        ]);

        // No signature header
        $this->client->request('POST', '/api/stripe/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $response = $this->client->getResponse();

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('Missing signature', $response->getContent());
    }

    public function testHandleCheckoutSessionCompletedWithOrderNotFound(): void
    {
        // Arrange: No order exists with this session ID
        $payload = json_encode([
            'id' => 'evt_test_webhook',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_nonexistent',
                ],
            ],
        ]);

        $signature = $this->generateStripeSignature($payload, $this->webhookSecret);

        // Act
        $this->client->request('POST', '/api/stripe/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);

        // Assert: Should still return 200 (webhook acknowledged)
        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleCheckoutSessionCompletedIdempotence(): void
    {
        // Arrange: Create an order that's already PAID
        $user = UserFactory::createOne()->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 10,
            'price' => '50.00',
        ])->_real();

        $paidAt = new \DateTimeImmutable('-1 hour');
        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::PAID, // Already paid!
            'stripeSessionId' => 'cs_test_already_paid',
            'stripePaymentIntentId' => 'pi_test_existing',
            'totalAmount' => '105.00',
            'paidAt' => $paidAt,
        ])->_real();

        OrderItemFactory::createOne([
            'parentOrder' => $order,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);

        $this->entityManager->flush();

        // Prepare webhook payload
        $payload = json_encode([
            'id' => 'evt_test_webhook_duplicate',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_already_paid',
                ],
            ],
        ]);

        $signature = $this->generateStripeSignature($payload, $this->webhookSecret);

        // Act: Send webhook again
        $this->client->request('POST', '/api/stripe/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);

        // Assert
        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->fail("Expected 200 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        // Assert: Order status unchanged
        $this->entityManager->refresh($order);
        $this->assertEquals(OrderStatus::PAID, $order->getStatus());
        $this->assertEqualsWithDelta($paidAt->getTimestamp(), $order->getPaidAt()->getTimestamp(), 1);

        // Assert: Stock was NOT decremented again
        $this->entityManager->refresh($product);
        $this->assertEquals(10, $product->getStock()); // Unchanged
    }

    public function testHandleUnknownEventType(): void
    {
        $payload = json_encode([
            'id' => 'evt_test_unknown',
            'type' => 'some.unknown.event',
            'data' => [
                'object' => [],
            ],
        ]);

        $signature = $this->generateStripeSignature($payload, $this->webhookSecret);

        $this->client->request('POST', '/api/stripe/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);

        $response = $this->client->getResponse();

        // Should return 200 OK (acknowledged but ignored)
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleCheckoutSessionCompletedWithInsufficientStock(): void
    {
        // Arrange: Create order but product has insufficient stock
        $user = UserFactory::createOne()->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 1, // Only 1 in stock
            'price' => '50.00',
        ])->_real();

        $order = OrderFactory::createOne([
            'user' => $user,
            'status' => OrderStatus::PENDING,
            'stripeSessionId' => 'cs_test_insufficient_stock',
            'totalAmount' => '105.00',
        ])->_real();

        OrderItemFactory::createOne([
            'parentOrder' => $order,
            'product' => $product,
            'quantity' => 5, // Trying to order 5 but only 1 available
            'unitPrice' => '50.00',
        ]);

        $this->entityManager->flush();

        // Mock StripeClient
        $stripeClientMock = $this->createMock(StripeClient::class);
        $checkoutSessionsMock = $this->createMock(\Stripe\Service\Checkout\SessionService::class);

        $checkoutMock = new class($checkoutSessionsMock) {
            public function __construct(public $sessions) {}
        };
        $stripeClientMock->checkout = $checkoutMock;

        $mockFullSession = new \stdClass();
        $mockFullSession->id = 'cs_test_insufficient_stock';
        $mockFullSession->payment_intent = 'pi_test_stock_issue';

        $checkoutSessionsMock
            ->expects($this->once())
            ->method('retrieve')
            ->with('cs_test_insufficient_stock')
            ->willReturn($mockFullSession);

        $this->client->getContainer()->set(StripeClient::class, $stripeClientMock);

        // Prepare webhook payload
        $payload = json_encode([
            'id' => 'evt_test_stock_issue',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_insufficient_stock',
                ],
            ],
        ]);

        $signature = $this->generateStripeSignature($payload, $this->webhookSecret);

        // Act
        $this->client->request('POST', '/api/stripe/webhook', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => $signature,
        ], $payload);

        // Assert: Webhook should still return 200
        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            $this->fail("Expected 200 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        // Assert: Order should be marked back to PENDING (for manual review)
        $this->entityManager->refresh($order);
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());
    }

    /**
     * Generate a valid Stripe webhook signature for testing
     */
    private function generateStripeSignature(string $payload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp . '.' . $payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}

<?php

namespace App\Tests\Controller\Api;

use App\Enum\ShippingMethod;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\CartFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\CartItemFactory;
use App\Tests\Factory\AddressFactory;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Checkout\Session as StripeSession;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CheckoutControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    #[DataProvider('provideShippingMethods')]
    public function testCreateCheckoutSession(ShippingMethod $shippingMethod, int $expectedAmount, string $sessionId, string $sessionUrl): void
    {
        // Arrange fixtures
        $user = UserFactory::createOne()->_real();
        $cart = CartFactory::createOne([
            'user' => $user,
            'totalPrice' => '100.00',
        ])->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 100,
            'price' => '50.00',
        ])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);
        $shippingAddress = AddressFactory::createOne(['user' => $user])->_real();
        $billingAddress = AddressFactory::createOne(['user' => $user])->_real();

        $this->entityManager->flush();

        // Mock StripePaymentService to avoid external calls
        $mockSession = new class($sessionId, $sessionUrl) extends StripeSession {
            public function __construct(public string $id, public string $url) {}
        };

        $stripePaymentServiceMock = $this->createMock(StripePaymentService::class);
        $stripePaymentServiceMock
            ->expects($this->once())
            ->method('createCheckoutSession')
            ->willReturn($mockSession);
        $stripePaymentServiceMock
            ->method('getPublicKey')
            ->willReturn('pk_test_dummy');

        $this->client->getContainer()->set(StripePaymentService::class, $stripePaymentServiceMock);
        $this->client->loginUser($user);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => $shippingMethod->value,
            'shippingAddressId' => $shippingAddress->getId(),
            'billingAddressId' => $billingAddress->getId(),
        ]));

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_CREATED) {
            $this->fail("Expected 201 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $responseData = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('sessionUrl', $responseData);
        $this->assertEquals($sessionUrl, $responseData['sessionUrl']);
    }

    #[DataProvider('provideInvalidAddresses')]
    public function testCreateCheckoutSessionWithInvalidAddresses(?int $shippingAddressId, ?int $billingAddressId, string $expectedErrorMessage): void
    {
        // Arrange fixtures
        $user = UserFactory::createOne()->_real();
        $cart = CartFactory::createOne([
            'user' => $user,
            'totalPrice' => '100.00',
        ])->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 100,
            'price' => '50.00',
        ])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);

        $shippingAddress = AddressFactory::createOne(['user' => $user])->_real();
        $billingAddress = AddressFactory::createOne(['user' => $user])->_real();

        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => ShippingMethod::STANDARD->value,
            'shippingAddressId' => $shippingAddressId,
            'billingAddressId' => $billingAddressId,
        ]));

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_BAD_REQUEST) {
            $this->fail("Expected 400 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString(strtolower($expectedErrorMessage), strtolower($responseData['error'] ?? $responseData['message'] ?? ''));
    }

    public function testCreateCheckoutSessionWithEmptyCart(): void
    {
        // Arrange fixtures
        $user = UserFactory::createOne()->_real();
        $cart = CartFactory::createOne([
            'user' => $user,
            'totalPrice' => '0.00',
        ])->_real();

        $shippingAddress = AddressFactory::createOne(['user' => $user])->_real();
        $billingAddress = AddressFactory::createOne(['user' => $user])->_real();

        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => ShippingMethod::STANDARD->value,
            'shippingAddressId' => $shippingAddress->getId(),
            'billingAddressId' => $billingAddress->getId(),
        ]));

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_BAD_REQUEST) {
            $this->fail("Expected 400 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $responseData = json_decode($response->getContent(), true);
        $this->assertStringContainsString('empty', strtolower($responseData['error'] ?? $responseData['message'] ?? ''));
    }

    public function testCreateCheckoutSessionWithoutAuthentication(): void
    {
        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => ShippingMethod::STANDARD->value,
            'shippingAddressId' => 1,
            'billingAddressId' => 1,
        ]));

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testCreateCheckoutSessionWithInvalidShippingMethod(): void
    {
        // Arrange fixtures
        $user = UserFactory::createOne()->_real();
        $cart = CartFactory::createOne([
            'user' => $user,
            'totalPrice' => '100.00',
        ])->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 100,
            'price' => '50.00',
        ])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);

        $shippingAddress = AddressFactory::createOne(['user' => $user])->_real();
        $billingAddress = AddressFactory::createOne(['user' => $user])->_real();

        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => 'invalid_method',
            'shippingAddressId' => $shippingAddress->getId(),
            'billingAddressId' => $billingAddress->getId(),
        ]));

        $response = $this->client->getResponse();
        $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testCreateCheckoutSessionWithOutOfStockProduct(): void
    {
        // Arrange fixtures
        $user = UserFactory::createOne()->_real();
        $cart = CartFactory::createOne([
            'user' => $user,
            'totalPrice' => '100.00',
        ])->_real();
        $product = ProductFactory::createOne([
            'isActive' => true,
            'stock' => 0,
            'price' => '50.00',
        ])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);

        $shippingAddress = AddressFactory::createOne(['user' => $user])->_real();
        $billingAddress = AddressFactory::createOne(['user' => $user])->_real();

        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => ShippingMethod::STANDARD->value,
            'shippingAddressId' => $shippingAddress->getId(),
            'billingAddressId' => $billingAddress->getId(),
        ]));

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_BAD_REQUEST) {
            $this->fail("Expected 400 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('stock', strtolower($data['error'] ?? $data['message'] ?? ''));
    }

    public function testCreateCheckoutSessionWithInactiveProduct(): void
    {
        // Arrange fixtures
        $user = UserFactory::createOne()->_real();
        $cart = CartFactory::createOne([
            'user' => $user,
            'totalPrice' => '100.00',
        ])->_real();
        $product = ProductFactory::createOne([
            'isActive' => false,
            'stock' => 100,
            'price' => '50.00',
        ])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $product,
            'quantity' => 2,
            'unitPrice' => '50.00',
        ]);

        $shippingAddress = AddressFactory::createOne(['user' => $user])->_real();
        $billingAddress = AddressFactory::createOne(['user' => $user])->_real();

        $this->entityManager->flush();

        $this->client->loginUser($user);

        $this->client->request('POST', '/api/checkout', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'shippingMethod' => ShippingMethod::STANDARD->value,
            'shippingAddressId' => $shippingAddress->getId(),
            'billingAddressId' => $billingAddress->getId(),
        ]));

        $response = $this->client->getResponse();

        if ($response->getStatusCode() !== Response::HTTP_BAD_REQUEST) {
            $this->fail("Expected 400 but got {$response->getStatusCode()}. Response: " . $response->getContent());
        }

        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('available', strtolower($data['error'] ?? $data['message'] ?? ''));
    }

    public static function provideShippingMethods(): array
    {
        return [
            'standard shipping' => [
                ShippingMethod::STANDARD,
                10299, // 100€ + 2.99€
                'cs_test_standard',
                'https://checkout.stripe.com/pay/cs_test_standard'
            ],
            'express shipping' => [
                ShippingMethod::EXPRESS,
                10499, // 100€ + 4.99€
                'cs_test_express',
                'https://checkout.stripe.com/pay/cs_test_express'
            ],
            'relay point shipping' => [
                ShippingMethod::RELAY_POINT,
                10000, // 100€ + 0€
                'cs_test_relay',
                'https://checkout.stripe.com/pay/cs_test_relay'
            ],
        ];
    }

    public static function provideInvalidAddresses(): array
    {
        return [
            'invalid shipping address' => [99999, 1, 'Invalid address provided'],
            'invalid billing address' => [1, 99999, 'Invalid address provided'],
        ];
    }
}

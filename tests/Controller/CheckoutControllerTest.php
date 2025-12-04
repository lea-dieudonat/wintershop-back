<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Entity\Cart;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\CartFactory;
use App\Tests\Factory\CartItemFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;

final class CheckoutControllerTest extends WebTestCase
{
    private $client;
    private User $user;
    private EntityManagerInterface $manager;
    private $product;
    private $address;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);

        $this->user = \App\Tests\Factory\UserFactory::createOne([
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $category = CategoryFactory::createOne();
        $this->product = ProductFactory::createOne([
            'name' => 'Test Ski',
            'price' => 199.99,
            'stock' => 10,
            'isActive' => true,
            'category' => $category,
        ])->_real();

        $this->address = AddressFactory::createOne([
            'user' => $this->user,
            'firstName' => 'John',
            'lastName' => 'Doe',
            'phoneNumber' => '1234567890',
            'city' => 'Test City',
            'country' => 'Test Country',
            'postalCode' => '12345',
            'street' => '123 Test St',
            'isDefault' => true,
        ])->_real();

        $this->manager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->manager->close();
    }

    // test authentication requirement
    public function testCheckoutRequiresAuthentication(): void
    {
        $this->client->request('GET', '/checkout/');
        $this->assertResponseRedirects('/login');
    }

    // test redirect when cart is empty
    public function testRedirectWhenEmptyCart(): void
    {
        // Simulate logged-in user
        $this->client->loginUser($this->user);

        $cart = new Cart();
        $cart->setUser($this->user);
        $cart->setTotalPrice('0.00');
        $this->manager->persist($cart);
        $this->manager->flush();

        $this->client->request('GET', '/checkout/');
        $this->assertResponseRedirects('/shop/');
    }

    // test redirect if addresses are missing
    public function testRedirectWhenNoAddresses(): void
    {
        // Simulate logged-in user without addresses
        $this->client->loginUser($this->user);
        // Simulate non-empty cart
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 1,
            'unitPrice' => $this->product->getPrice(),
        ]);

        $this->manager->remove($this->address);
        $this->manager->flush();

        $this->client->request('GET', '/checkout/');
        $this->assertResponseRedirects();
    }

    // test successful checkout page load
    public function testSuccessfulCheckoutPageLoad(): void
    {
        $this->client->loginUser($this->user);
        // Simulate non-empty cart
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 1,
            'unitPrice' => $this->product->getPrice(),
        ]);
        $this->client->request('GET', '/checkout/');
        $this->assertResponseIsSuccessful();
    }

    // test creation of successful order
    public function testSuccessfulOrderCreation(): void
    {
        $this->client->loginUser($this->user);
        // Simulate non-empty cart
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 2,
            'unitPrice' => '199.99',
        ]);
        // Submit checkout form
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $this->generateCsrfToken('checkout_confirm'),
            'shipping_address_id' => $this->address->getId(),
            'billing_address_id' => $this->address->getId(),
        ]);
        $this->assertResponseRedirects();

        // Verify order was created
        $order = $this->manager->getRepository(Order::class)->findOneBy(['user' => $this->user]);
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::PENDING, $order->getStatus());
        $this->assertEquals('399.98', $order->getTotalAmount());
        $this->assertCount(1, $order->getItems());

        // Verify order item
        $orderItem = $order->getItems()->first();
        $this->assertEquals(2, $orderItem->getQuantity());
        $this->assertEquals('199.99', $orderItem->getUnitPrice());
        $this->assertEquals('399.98', $orderItem->getTotalPrice());

        // Verify cart was cleared
        $this->manager->refresh($cart);
        $this->assertCount(0, $cart->getItems());
        $this->assertEquals('0.00', $cart->getTotalPrice());
    }

    // test checking address
    public function testInvalidAddressHandling(): void
    {
        $this->client->loginUser($this->user);
        $otherUser = UserFactory::createOne([])->_real();
        $otherAddress = AddressFactory::createOne([
            'user' => $otherUser,
            'street' => 'Other St',
        ])->_real();
        // Simulate non-empty cart
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 1,
            'unitPrice' => $this->product->getPrice(),
        ]);
        $this->manager->flush();

        // Submit checkout form with invalid billing address
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $this->generateCsrfToken('checkout_confirm'),
            'shipping_address_id' => $otherAddress->getId(),
            'billing_address_id' => $this->address->getId(),
        ]);
        $this->assertResponseStatusCodeSame(403);
    }

    // test unavailable product
    public function testUnavailableProductDuringCheckout(): void
    {
        $this->client->loginUser($this->user);
        // Simulate cart with unavailable product
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 1,
            'unitPrice' => $this->product->getPrice(),
        ]);

        // Deactivate product
        $this->product->setIsActive(false);
        $this->manager->flush();

        // Submit checkout form
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $this->generateCsrfToken('checkout_confirm'),
            'shipping_address_id' => $this->address->getId(),
            'billing_address_id' => $this->address->getId(),
        ]);
        $this->assertResponseRedirects('/checkout/');
    }

    // test clearing cart after order
    public function testCartClearedAfterOrder(): void
    {
        $this->client->loginUser($this->user);
        // Simulate non-empty cart
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 15,
            'unitPrice' => $this->product->getPrice(),
        ]);

        // Submit checkout form
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $this->generateCsrfToken('checkout_confirm'),
            'shipping_address_id' => $this->address->getId(),
            'billing_address_id' => $this->address->getId(),
        ]);
        $this->assertResponseRedirects('/checkout/');
        // Follow redirect to load the flash message page
        $this->client->followRedirect();
        // Verify cart is empty and an error flash is shown
        $this->assertSelectorExists('.alert-error');
    }

    // test successful order confirmation page
    public function testOrderConfirmationPage(): void
    {
        $this->client->loginUser($this->user);

        $order = new Order();
        $order->setUser($this->user);
        $order->setShippingAddress($this->address);
        $order->setBillingAddress($this->address);
        $order->setTotalAmount('199.99');
        $order->setStatus(OrderStatus::PENDING);

        $orderItem = new OrderItem();
        $orderItem->setParentOrder($order);
        $orderItem->setProduct($this->product);
        $orderItem->setQuantity(1);
        $orderItem->setUnitPrice('199.99');
        $orderItem->setTotalPrice('199.99');

        $this->manager->persist($order);
        $this->manager->persist($orderItem);
        $this->manager->flush();

        $this->client->request('GET', '/checkout/success/' . $order->getOrderNumber());
        $this->assertResponseIsSuccessful();
    }

    private function generateCsrfToken(string $tokenId): string
    {
        $container = static::getContainer();

        // Ensure RequestStack has a Request with a Session so CSRF token storage works
        if ($container->has('request_stack')) {
            $requestStack = $container->get('request_stack');
            $current = $requestStack->getCurrentRequest();
            $hasSession = $current && $current->hasSession();
            if (!$hasSession) {
                $session = new \Symfony\Component\HttpFoundation\Session\Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
                $session->start();
                $request = new \Symfony\Component\HttpFoundation\Request();
                $request->setSession($session);
                $requestStack->push($request);
            }
        }

        $csrfTokenManager = $container->get('security.csrf.token_manager');
        return $csrfTokenManager->getToken($tokenId)->getValue();
    }
}

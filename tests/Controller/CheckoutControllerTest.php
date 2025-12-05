<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Entity\User;
use App\Tests\Factory\AddressFactory;
use App\Tests\Factory\CartFactory;
use App\Tests\Factory\CartItemFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\OrderFactory;
use App\Tests\Factory\OrderItemFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Order;
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

        $this->user = UserFactory::createOne([
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
        $this->client->loginUser($this->user);

        CartFactory::createOne([
            'user' => $this->user,
            'totalPrice' => '0.00',
        ]);

        $this->client->request('GET', '/checkout/');
        $this->assertResponseRedirects('/shop/');
    }

    // test redirect if addresses are missing
    public function testRedirectWhenNoAddresses(): void
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

        // Load the checkout page to get the CSRF token from the form
        $crawler = $this->client->request('GET', '/checkout/');
        $this->assertResponseIsSuccessful();

        // Extract CSRF token from the hidden input field
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        // Submit checkout form with the extracted token
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $token,
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

        // Verify cart was cleared - fetch fresh from DB
        $cartFromDb = $this->manager->getRepository(\App\Entity\Cart::class)->findOneBy(['user' => $this->user]);
        $this->assertNotNull($cartFromDb);
        $this->assertCount(0, $cartFromDb->getItems());
        $this->assertEquals('0.00', $cartFromDb->getTotalPrice());
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

        // Load checkout page and extract CSRF token
        $crawler = $this->client->request('GET', '/checkout/');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        // Submit checkout form with invalid billing address
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $token,
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

        // Load checkout page first (while product is still active)
        $crawler = $this->client->request('GET', '/checkout/');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        // Now deactivate product (simulating product becoming unavailable between page load and submit)
        $this->product->setIsActive(false);
        $this->manager->flush();

        // Submit checkout form with the token we got earlier
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $token,
            'shipping_address_id' => $this->address->getId(),
            'billing_address_id' => $this->address->getId(),
        ]);
        $this->assertResponseRedirects('/checkout/');
    }

    // test clearing cart after order (when stock is insufficient)
    public function testCartClearedAfterOrder(): void
    {
        $this->client->loginUser($this->user);
        // Create cart with valid quantity first (so we can load checkout page)
        $cart = CartFactory::createOne(['user' => $this->user])->_real();
        $cartItem = CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 5, // Valid quantity initially
            'unitPrice' => $this->product->getPrice(),
        ])->_real();

        // Load checkout page and extract CSRF token
        $crawler = $this->client->request('GET', '/checkout/');
        $token = $crawler->filter('input[name="_csrf_token"]')->attr('value');

        // Now change quantity to exceed stock (simulating user manipulation or race condition)
        $cartItem->setQuantity(15); // More than available stock (10)
        $this->manager->flush();

        // Submit checkout form
        $this->client->request('POST', '/checkout/confirm', [
            '_token' => $token,
            'shipping_address_id' => $this->address->getId(),
            'billing_address_id' => $this->address->getId(),
        ]);
        $this->assertResponseRedirects('/checkout/');

        // Follow redirect - this will check for unavailable products and redirect to cart
        $crawler = $this->client->followRedirect();

        // The checkout page detects unavailable products and redirects to cart
        $this->assertResponseRedirects('/cart/');

        // Follow second redirect to cart page where error is shown
        $this->client->followRedirect();

        // Verify error flash is shown on cart page
        $this->assertSelectorExists('.flash-error');
    }

    // test successful order confirmation page
    public function testOrderConfirmationPage(): void
    {
        $this->client->loginUser($this->user);

        // Create order with item using factories for consistency
        $order = OrderFactory::createOne([
            'user' => $this->user,
            'shippingAddress' => $this->address,
            'billingAddress' => $this->address,
            'totalAmount' => '199.99',
            'status' => OrderStatus::PENDING,
        ])->_real();

        OrderItemFactory::createOne([
            'parentOrder' => $order,
            'product' => $this->product,
            'quantity' => 1,
            'unitPrice' => '199.99',
            'totalPrice' => '199.99',
        ]);

        $this->client->request('GET', '/checkout/success/' . $order->getOrderNumber());
        $this->assertResponseIsSuccessful();
    }
}

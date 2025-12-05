<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Cart;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\CategoryFactory;
use App\Tests\Factory\CartFactory;
use App\Tests\Factory\CartItemFactory;

final class CartControllerTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $manager;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);

        // Cleanup is handled by DamaDoctrineTestBundle (transaction rollback); manual purges removed.

        // Create fresh test user
        $this->user = UserFactory::createOne([
            'email' => 'testuser@example.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        // Create test product with category
        $category = CategoryFactory::createOne();
        $this->product = ProductFactory::createOne([
            'price' => 99.99,
            'stock' => 10,
            'isActive' => true,
            'category' => $category,
        ])->_real();

        $this->manager->flush();
    }

    public function testCartIndexRequiresAuthentication(): void
    {
        $this->client->request('GET', '/cart/');
        $this->assertResponseRedirects('/login');
    }

    public function testCartIndexDisplaysEmptyCart(): void
    {
        $this->client->loginUser($this->user);
        $this->client->request('GET', '/cart/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.empty-cart', 'Votre panier est vide.');
    }

    public function testAddToCart(): void
    {
        // Test adding a product to the cart
        $this->client->loginUser($this->user);
        $this->client->request('POST', '/cart/add/' . $this->product->getId(), ['quantity' => 2]);
        $this->assertResponseRedirects('/cart/');

        // Check that the cart now contains the product
        $cart = $this->manager->getRepository(Cart::class)->findOneBy(['user' => $this->user]);
        $this->assertNotNull($cart);
        $this->assertCount(1, $cart->getItems());
        $cartItem = $cart->getItems()->first();
        $this->assertEquals($this->product->getId(), $cartItem->getProduct()->getId());
        $this->assertEquals(2, $cartItem->getQuantity());
        $this->assertEquals(99.99, $cartItem->getUnitprice());
    }

    public function testAddProductAlreadyInCart(): void
    {
        // Test adding a product that is already in the cart
        $this->client->loginUser($this->user);
        $this->client->request('POST', '/cart/add/' . $this->product->getId(), ['quantity' => 2]);
        $this->client->request('POST', '/cart/add/' . $this->product->getId(), ['quantity' => 3]);
        $this->assertResponseRedirects('/cart/');

        // Check that the cart item quantity has been updated
        $cart = $this->manager->getRepository(Cart::class)->findOneBy(['user' => $this->user]);
        $this->assertNotNull($cart);
        $this->assertCount(1, $cart->getItems());
        $cartItem = $cart->getItems()->first();
        $this->assertEquals(5, $cartItem->getQuantity());
    }

    public function testAddProductWithInsufficientStock(): void
    {
        // Test adding a product with quantity exceeding stock
        $this->client->loginUser($this->user);
        $this->client->request('POST', '/cart/add/' . $this->product->getId(), ['quantity' => 20]);
        $this->assertResponseRedirects('/shop/product/' . $this->product->getSlug());

        // Check that the cart is still empty
        $cart = $this->manager->getRepository(Cart::class)->findOneBy(['user' => $this->user]);
        if ($cart) {
            $this->assertCount(0, $cart->getItems());
        }
    }

    public function testAddProductWithInvalidQuantity(): void
    {
        // Test adding a product with invalid quantity
        $this->client->loginUser($this->user);
        $this->client->request('POST', '/cart/add/' . $this->product->getId(), ['quantity' => 0]);
        $this->assertResponseRedirects('/shop/product/' . $this->product->getSlug());

        // Check that the cart is still empty
        $cart = $this->manager->getRepository(Cart::class)->findOneBy(['user' => $this->user]);
        if ($cart) {
            $this->assertCount(0, $cart->getItems());
        }
    }

    public function testUpdateCartItem(): void
    {
        // Test updating the quantity of a cart item
        $this->client->loginUser($this->user);

        // Create a cart with an item using factories
        $cart = CartFactory::createOne(['user' => $this->user]);
        $cartItem = CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 2,
        ]);

        // Update the quantity
        $this->client->request('POST', '/cart/update/' . $cartItem->getId(), [
            'quantity' => 5,
        ]);

        $this->assertResponseRedirects('/cart/');

        // Verify the update
        $this->manager->refresh($cartItem->_real());
        $this->assertEquals(5, $cartItem->getQuantity());
    }

    public function testUpdateCartItemWithInsufficientStock(): void
    {
        // Test updating a cart item with quantity exceeding stock
        $this->client->loginUser($this->user);

        // Create a cart with an item using factories
        $cart = CartFactory::createOne(['user' => $this->user]);
        $cartItem = CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 2,
        ]);

        // Attempt to update the quantity beyond stock
        $this->client->request('POST', '/cart/update/' . $cartItem->getId(), [
            'quantity' => 20,
        ]);

        $this->assertResponseRedirects('/cart/');

        // Verify that the quantity has not changed
        $this->manager->refresh($cartItem->_real());
        $this->assertEquals(2, $cartItem->getQuantity());
    }

    public function testRemoveFromCart(): void
    {
        // Test removing an item from the cart
        $this->client->loginUser($this->user);

        // Create a cart with an item using factories
        $cart = CartFactory::createOne(['user' => $this->user]);
        $cartItem = CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 2,
        ]);

        // Remove the item
        $this->client->request('POST', '/cart/remove/' . $cartItem->getId());

        $this->assertResponseRedirects('/cart/');

        // Verify the removal by reloading the cart from the repository
        $freshCart = $this->manager->getRepository(Cart::class)->find($cart->getId());
        $this->assertNotNull($freshCart);
        $this->assertCount(0, $freshCart->getItems());
    }

    public function testClearCart(): void
    {
        // Test clearing the cart
        $this->client->loginUser($this->user);

        // Create a cart with items using factories
        $cart = CartFactory::createOne(['user' => $this->user]);
        CartItemFactory::createMany(3, [
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 2,
        ]);

        // Clear the cart
        $this->client->request('POST', '/cart/clear');

        $this->assertResponseRedirects('/cart/');

        // Verify the cart is empty by reloading it from the repository
        $freshCart = $this->manager->getRepository(Cart::class)->find($cart->getId());
        $this->assertNotNull($freshCart);
        $this->assertCount(0, $freshCart->getItems());
    }

    public function testCannotUpdateOtherUserCart(): void
    {
        // Test that a user cannot update another user's cart item
        $this->client->loginUser($this->user);

        // Create another user and their cart item
        $otherUser = UserFactory::createOne([
            'email' => 'otheruser@example.com',
            'roles' => ['ROLE_USER'],
        ])->_real();
        $otherCart = CartFactory::createOne(['user' => $otherUser]);
        $otherCartItem = CartItemFactory::createOne([
            'cart' => $otherCart,
            'product' => $this->product,
            'quantity' => 2,
        ]);
        // Attempt to update the other user's cart item
        // Ensure the client converts exceptions to HTTP responses so we can assert 403
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/cart/update/' . $otherCartItem->getId(), [
            'quantity' => 5,
        ]);

        $this->assertResponseStatusCodeSame(403); // Forbidden
    }

    public function testCannotRemoveOtherUserCartItem(): void
    {
        // Test that a user cannot remove another user's cart item
        $this->client->loginUser($this->user);

        // Create another user and their cart item
        $otherUser = UserFactory::createOne([
            'email' => 'otheruser@example.com',
            'roles' => ['ROLE_USER'],
        ])->_real();
        $otherCart = CartFactory::createOne(['user' => $otherUser]);
        $otherCartItem = CartItemFactory::createOne([
            'cart' => $otherCart,
            'product' => $this->product,
            'quantity' => 2,
        ]);
        // Attempt to remove the other user's cart item
        // Ensure the client converts exceptions to HTTP responses so we can assert 403
        $this->client->catchExceptions(true);
        $this->client->request('POST', '/cart/remove/' . $otherCartItem->getId());

        $this->assertResponseStatusCodeSame(403); // Forbidden
    }

    public function testCartDisplaysCorrectTotalPrice(): void
    {
        // Test that the cart displays the correct total price
        $this->client->loginUser($this->user);

        $product2 = ProductFactory::createOne([
            'price' => 49.99,
            'stock' => 10,
            'isActive' => true,
            'category' => CategoryFactory::createOne(),
        ])->_real();

        // Create cart with items using factories (same pattern as other tests)
        $cart = CartFactory::createOne(['user' => $this->user])->_real();

        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $this->product,
            'quantity' => 2,
            'unitPrice' => (string) $this->product->getPrice(),
        ]);

        CartItemFactory::createOne([
            'cart' => $cart,
            'product' => $product2,
            'quantity' => 1,
            'unitPrice' => (string) $product2->getPrice(),
        ]);

        $this->manager->flush();

        // Access the cart page
        $this->client->request('GET', '/cart/');

        $this->assertResponseIsSuccessful();

        // Calculate expected total
        $expectedTotal = (2 * 99.99) + (1 * 49.99);

        // Check that the total price is displayed correctly
        // Template appends the euro symbol, so include it in the assertion
        $this->assertSelectorTextContains('.cart-total', number_format($expectedTotal, 2, '.', '') . ' €');
    }
}

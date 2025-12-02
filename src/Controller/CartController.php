<?php

namespace App\Controller;

use App\Constant\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\CartItem;
use App\Repository\CartRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Annotation\Route as RouteAttribute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Cart;
use App\Entity\Product;
use App\Entity\User;

#[RouteAttribute('/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository,
        private ProductRepository $productRepository,
    )
    {
    }

    #[RouteAttribute('/', name: Route::CART->value, methods: ['GET'])]
    public function index(): Response
    {
        $cart = $this->getOrCreateCart();

        // Recalculate totalPrice for display in case items were created
        // outside of controller (fixtures/factories) and DB snapshot isn't
        // up-to-date with the Cart.totalPrice field.
        $totalFloat = 0.0;
        foreach ($cart->getItems() as $item) {
            $totalFloat += (float) $item->getProduct()->getPrice() * $item->getQuantity();
        }
        $cart->setTotalPrice(number_format($totalFloat, 2, '.', ''));

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
        ]);
    }

    #[RouteAttribute('/add/{id}', name: Route::CART_ADD->value, methods: ['POST'])]
    public function add(Request $request, Product $product): Response
    {
        // read quantity from POST body if provided (default to 1)
        $quantity = (int) $request->request->get('quantity', 1);

        if ($quantity < 1) {
            $this->addFlash('error', 'Quantity must be at least 1.');
            return $this->redirectToRoute(Route::SHOP_PRODUCT->value, ['slug' => $product->getSlug()]);
        }

        if ($product->getStock() < $quantity) {
            $this->addFlash('error', 'Not enough stock available.');
            return $this->redirectToRoute(Route::SHOP_PRODUCT->value, ['slug' => $product->getSlug()]);
        }

        $cart = $this->getOrCreateCart();

        // Check if the product is already in the cart
        $cartItem = null;
        foreach ($cart->getItems() as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $cartItem = $item;
                break;
            }
        }

        if ($cartItem) {
            // Update quantity if item already in cart
            $newQuantity = $cartItem->getQuantity() + $quantity;
            if ($product->getStock() < $newQuantity) {
                $this->addFlash('error', 'Not enough stock available to add that quantity.');
                return $this->redirectToRoute(Route::SHOP_PRODUCT->value, ['slug' => $product->getSlug()]);
            }
            $cartItem->setQuantity($newQuantity);
        } else {
            // Create new cart item
            $cartItem = new CartItem();
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            // Store the unit price at time of adding to cart to avoid null DB value
            $cartItem->setUnitPrice((string) $product->getPrice());
            $cart->addItem($cartItem);
            $this->entityManager->persist($cartItem);
        }
        $cart->setUpdatedAt(new \DateTimeImmutable());

        // Recalculate cart total price
        $total = '0.00';
        foreach ($cart->getItems() as $item) {
            $line = \bcmul($item->getUnitPrice(), (string) $item->getQuantity(), 2);
            $total = \bcadd($total, $line, 2);
        }
        $cart->setTotalPrice($total);

        $this->entityManager->flush();
        $this->addFlash('success', 'Product added to cart successfully.');
        return $this->redirectToRoute(Route::CART->value);
    }

    #[RouteAttribute('/update/{id}', name: Route::CART_UPDATE->value, methods: ['POST'])]
    public function update(Request $request, CartItem $cartItem): Response
    {
        // Ensure the cart item belongs to the current user's cart
        $cart = $this->getOrCreateCart();
        if ($cartItem->getCart()->getId() !== $cart->getId()) {
            throw $this->createAccessDeniedException('You do not have permission to modify this cart item.');
        }

        // read quantity from POST body if provided (default to 1)
        $quantity = (int) $request->request->get('quantity', 1);

        if ($quantity < 1) {
            $this->addFlash('error', 'Quantity must be at least 1.');
            return $this->redirectToRoute(Route::CART->value);
        }

        if ($cartItem->getProduct()->getStock() < $quantity) {
            $this->addFlash('error', 'Not enough stock available.');
            return $this->redirectToRoute(Route::CART->value);
        }

        $cartItem->setQuantity($quantity);
        $cart->setUpdatedAt(new \DateTimeImmutable());

        // Recalculate cart total price
        $cart = $cartItem->getCart();
        $total = '0.00';
        foreach ($cart->getItems() as $item) {
            $line = \bcmul($item->getUnitPrice(), (string) $item->getQuantity(), 2);
            $total = \bcadd($total, $line, 2);
        }
        $cart->setTotalPrice($total);

        $this->entityManager->flush();

        $this->addFlash('success', 'Cart updated successfully.');
        return $this->redirectToRoute(Route::CART->value);
    }

    #[RouteAttribute('/remove/{id}', name: Route::CART_REMOVE->value, methods: ['POST'])]
    public function remove(CartItem $cartItem): Response
    {
        // Ensure the cart item belongs to the current user's cart
        $cart = $this->getOrCreateCart();
        if ($cartItem->getCart()->getId() !== $cart->getId()) {
            throw $this->createAccessDeniedException('You do not have permission to modify this cart item.');
        }

        $cart->removeItem($cartItem);
        $cart->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->remove($cartItem);

        // Recalculate cart total price after removal
        $total = '0.00';
        foreach ($cart->getItems() as $item) {
            $line = \bcmul($item->getUnitPrice(), (string) $item->getQuantity(), 2);
            $total = \bcadd($total, $line, 2);
        }
        $cart->setTotalPrice($total);

        $this->entityManager->flush();

        $this->addFlash('success', 'Item removed from cart successfully.');
        return $this->redirectToRoute(Route::CART->value);
    }

    #[RouteAttribute('/clear', name: Route::CART_CLEAR->value, methods: ['POST'])]
    public function clear(): Response
    {
        $cart = $this->getOrCreateCart();

        // Use a direct DQL delete to ensure items are removed in the DB even if
        // they were persisted/managed by a different EM instance used in tests.
        $this->entityManager->createQuery('DELETE FROM App\\Entity\\CartItem ci WHERE ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->execute();

        // Clear the collection in the owning Cart entity and update totals
        $cart->getItems()->clear();
        $cart->setUpdatedAt(new \DateTimeImmutable());
        $cart->setTotalPrice('0.00');

        $this->entityManager->flush();

        $this->addFlash('success', 'Cart cleared successfully.');
        return $this->redirectToRoute(Route::CART->value);
    }

    // Helper method to get or create a cart for the current user/session
    public function getOrCreateCart(): Cart
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to access the cart.');
        }

        // Ensure we use an entity instance managed by this controller's EntityManager
        /** @var User $user */
        $user = $user;
        $userId = $user->getId();
        $managedUser = $this->entityManager->getRepository(User::class)->find($userId);

        if (!$managedUser) {
            throw $this->createAccessDeniedException('User not found.');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $managedUser]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($managedUser);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }
}
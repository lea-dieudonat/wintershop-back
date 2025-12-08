<?php

namespace App\Controller;

use App\Constant\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\CartItem;
use App\Service\CartService;
use Symfony\Component\Routing\Annotation\Route as RouteAttribute;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Product;

#[RouteAttribute('/cart')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService,
    ) {}

    #[RouteAttribute('/', name: Route::CART->value, methods: ['GET'])]
    public function index(): Response
    {
        $cart = $this->cartService->getOrCreateCart();

        $this->cartService->calculateTotal($cart);

        $unavailableItems = $this->cartService->removeUnavailableItems($cart);

        if (count($unavailableItems) > 0) {
            $this->addFlash('warning', sprintf(
                '%d produit(s) ont été retirés de votre panier car ils ne sont plus disponibles.',
                count($unavailableItems)
            ));
        }

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'unavailableItems' => $unavailableItems,
        ]);
    }

    #[RouteAttribute('/add/{id}', name: Route::CART_ADD->value, methods: ['POST'])]
    public function add(Request $request, Product $product): Response
    {
        $submittedToken = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('add-to-cart' . $product->getId(), $submittedToken)) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute(Route::SHOP_PRODUCT->value, ['slug' => $product->getSlug()]);
        }

        $quantity = (int) $request->request->get('quantity', 1);
        $cart = $this->cartService->getOrCreateCart();

        try {
            $this->cartService->addProduct($cart, $product, $quantity);
            $this->addFlash('success', 'Product added to cart successfully.');
            // Redirection vers la page d'où vient l'utilisateur
            $referer = $request->headers->get('referer');
            if ($referer) {
                return $this->redirect($referer);
            }
            return $this->redirectToRoute(Route::CART->value);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute(Route::SHOP_PRODUCT->value, ['slug' => $product->getSlug()]);
        }
    }

    #[RouteAttribute('/update/{id}', name: Route::CART_UPDATE->value, methods: ['POST'])]
    public function update(Request $request, CartItem $cartItem): Response
    {
        $quantity = (int) $request->request->get('quantity', 1);
        $cart = $this->cartService->getOrCreateCart();

        try {
            $this->cartService->updateQuantity($cart, $cartItem, $quantity);
            $this->addFlash('success', 'Cart updated successfully.');
            return $this->redirectToRoute(Route::CART->value);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute(Route::CART->value);
        }
    }

    #[RouteAttribute('/remove/{id}', name: Route::CART_REMOVE->value, methods: ['POST'])]
    public function remove(CartItem $cartItem): Response
    {
        $cart = $this->cartService->getOrCreateCart();

        $this->cartService->removeProduct($cart, $cartItem);
        $this->addFlash('success', 'Item removed from cart successfully.');
        return $this->redirectToRoute(Route::CART->value);
    }

    #[RouteAttribute('/clear', name: Route::CART_CLEAR->value, methods: ['POST'])]
    public function clear(): Response
    {
        $cart = $this->cartService->getOrCreateCart();

        $this->cartService->clearCart($cart);

        $this->addFlash('success', 'Cart cleared successfully.');
        return $this->redirectToRoute(Route::CART->value);
    }
}

<?php

namespace App\Controller;

use App\Constant\Route;
use App\Entity\Cart;
use App\Entity\User;
use App\Entity\Address;
use App\Service\CartService;
use App\Service\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route as RouteAttribute;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[RouteAttribute('/checkout')]
#[IsGranted('ROLE_USER')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CheckoutService $checkoutService,
        private CartService $cartService
    ) {}

    #[RouteAttribute('/', name: Route::CHECKOUT->value)]
    public function index(): Response
    {
        $cart = $this->getCart();

        // Check if cart is empty before checking products
        if ($cart->getItems()->isEmpty()) {
            $this->addFlash('warning', 'Your cart is empty. Add some products before checkout.');
            return $this->redirectToRoute(Route::SHOP->value);
        }

        $this->cartService->calculateTotal($cart);

        // Check for unavailable items - if any are removed, redirect to cart
        $unavailableItems = $this->cartService->removeUnavailableItems($cart);
        if (!empty($unavailableItems)) {
            $this->addFlash('error', 'Certains produits de votre panier ne sont plus disponibles. Veuillez vérifier votre panier.');
            return $this->redirectToRoute(Route::CART->value);
        }

        /** @var User $user */
        $user = $this->getUser();
        $addresses = $user->getAddresses();

        if ($addresses->isEmpty()) {
            $this->addFlash('info', 'Please add a shipping address before checkout.');
            //return $this->redirectToRoute('profile_address_new'); TODO
            return $this->redirectToRoute(Route::SHOP->value);
        }

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
            'addresses' => $addresses,
        ]);
    }

    #[RouteAttribute('/confirm', name: Route::CHECKOUT_CONFIRM->value, methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        $cart = $this->getCart();

        if ($cart->getItems()->isEmpty()) {
            $this->addFlash('error', 'Cannot checkout with an empty cart.');
            return $this->redirectToRoute(Route::CART->value);
        }

        // Get selected addresses from form
        $shippingAddressId = $request->request->get('shipping_address_id');
        $billingAddressId = $request->request->get('billing_address_id');

        if (!$shippingAddressId || !$billingAddressId) {
            $this->addFlash('error', 'Please select shipping and billing addresses.');
            return $this->redirectToRoute(Route::CHECKOUT->value);
        }

        // Verify addresses belong to current user
        /** @var User $user */
        $user = $this->getUser();

        $shippingAddress = $this->entityManager->getRepository(Address::class)->find($shippingAddressId);
        $billingAddress = $this->entityManager->getRepository(Address::class)->find($billingAddressId);

        if (!$shippingAddress || $shippingAddress->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Invalid shipping address.');
        }

        if (!$billingAddress || $billingAddress->getUser()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('Invalid billing address.');
        }

        try {
            // Create order from cart
            $order = $this->checkoutService->createOrderFromCart(
                $cart,
                $shippingAddress,
                $billingAddress
            );

            // Clear the cart
            $this->cartService->clearCart($cart);

            $this->addFlash('success', sprintf(
                'Order %s created successfully! You will receive a confirmation email.',
                $order->getReference()
            ));

            return $this->redirectToRoute(Route::CHECKOUT_SUCCESS->value, [
                'reference' => $order->getReference()
            ]);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute(Route::CHECKOUT->value);
        }
    }

    #[RouteAttribute('/success/{reference}', name: Route::CHECKOUT_SUCCESS->value)]
    public function success(string $reference): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $order = $this->entityManager->getRepository(\App\Entity\Order::class)
            ->findOneBy(['reference' => $reference, 'user' => $user]);

        if (!$order) {
            throw $this->createNotFoundException('Order not found.');
        }

        return $this->render('checkout/success.html.twig', [
            'order' => $order,
        ]);
    }

    private function getCart(): Cart
    {
        /** @var User $user */
        $user = $this->getUser();
        $cart = $user->getCart();

        if (!$cart) {
            throw new \RuntimeException('Cart not found for current user.');
        }

        return $cart;
    }
}

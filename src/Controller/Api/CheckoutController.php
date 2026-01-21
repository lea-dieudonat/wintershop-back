<?php

namespace App\Controller\Api;

use App\Dto\Checkout\CheckoutInputDto;
use App\Dto\Checkout\CheckoutSessionOutputDto;
use App\Entity\Address;
use App\Entity\Cart;
use App\Repository\AddressRepository;
use App\Repository\CartRepository;
use App\Service\CheckoutService;
use App\Service\StripePaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/api/checkout', name: 'api_checkout_')]
class CheckoutController extends AbstractController
{
    public function __construct(
        private CheckoutService $checkoutService,
        private StripePaymentService $stripePaymentService,
        private CartRepository $cartRepository,
        private AddressRepository $addressRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {}

    /**
     * Create an order and initiate Stripe payment
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function checkout(
        #[MapRequestPayload] CheckoutInputDto $checkoutInputDto
    ): JsonResponse {
        $user = $this->security->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        // Retrieve user's cart
        $cart = $this->cartRepository->findOneBy(['user' => $user]);

        if (!$cart || $cart->getItems()->isEmpty()) {
            return $this->json(
                ['error' => 'Cart is empty.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        // Retrieve addresses
        $shippingAddress = $this->addressRepository->find($checkoutInputDto->shippingAddressId);
        $billingAddress = $this->addressRepository->find($checkoutInputDto->billingAddressId);

        if (!$shippingAddress || $shippingAddress->getUser() !== $user) {
            return $this->json(
                ['error' => 'Invalid address provided.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if (!$billingAddress || $billingAddress->getUser() !== $user) {
            return $this->json(
                ['error' => 'Invalid address provided.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        try {
            // Create order from cart
            $order = $this->checkoutService->createOrderFromCart(
                $cart,
                $shippingAddress,
                $billingAddress,
                $checkoutInputDto->shippingMethod
            );

            // Create Stripe checkout session
            $checkoutSession = $this->stripePaymentService->createCheckoutSession($order);

            // Save Stripe session ID to order
            $order->setStripeSessionId($checkoutSession->id);
            $this->entityManager->flush();

            // Clear cart after successful order creation
            foreach ($cart->getItems() as $item) {
                $this->entityManager->remove($item);
            }
            $cart->setTotalPrice('0.00');
            $this->entityManager->flush();

            // Return session ID to frontend
            $outputDto = new CheckoutSessionOutputDto(
                sessionId: $checkoutSession->id,
                sessionUrl: $checkoutSession->url,
                orderId: $order->getId(),
                orderReference: $order->getReference(),
                publicKey: $this->stripePaymentService->getPublicKey()
            );

            return $this->json($outputDto, Response::HTTP_CREATED);
        } catch (\RuntimeException $e) {
            return $this->json(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST
            );
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'An unexpected error occurred. Please try again later.'],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * Success callback after payment
     */
    #[Route('/success/{orderId}', name: 'success', methods: ['GET'])]
    public function checkoutSuccess(int $orderId): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->security->getUser();

        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // In a real app, you'd verify the payment status via webhook
        // For now, just return success with order info
        return $this->json([
            'message' => 'Payment successful!',
            'orderId' => $orderId,
        ]);
    }

    /**
     * Cancel callback after payment
     */
    #[Route('/cancel/{orderId}', name: 'cancel', methods: ['GET'])]
    public function checkoutCancel(int $orderId): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->security->getUser();
        if (!$user) {
            return $this->json(['error' => 'User not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'message' => 'Payment cancelled.',
            'orderId' => $orderId,
        ], Response::HTTP_OK);
    }
}

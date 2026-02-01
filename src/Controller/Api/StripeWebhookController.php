<?php

namespace App\Controller\Api;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use App\Service\CheckoutService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private StripeClient $stripeClient,
        private LoggerInterface $logger,
        private CheckoutService $checkoutService,
        private string $webhookSecret
    ) {}

    /**
     * Handle incoming Stripe webhooks
     */
    #[Route('/api/stripe/webhook', name: 'stripe_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        if (!$sigHeader) {
            $this->logger->error('Stripe webhook: Missing signature header');
            return new Response('Missing signature', Response::HTTP_BAD_REQUEST);
        }

        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                $this->webhookSecret
            );
        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Stripe webhook payload invalid: ' . $e->getMessage());
            return new Response('Invalid payload', Response::HTTP_BAD_REQUEST);
        } catch (SignatureVerificationException $e) {
            $this->logger->error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);
        }

        // Handle the event
        try {
            switch ($event->type) {
                case 'checkout.session.completed':
                    $this->handleCheckoutSessionCompleted($event->data->object);
                    break;

                case 'payment_intent.succeeded':
                    $this->handlePaymentIntentSucceeded($event->data->object);
                    break;

                case 'payment_intent.payment_failed':
                    $this->handlePaymentIntentFailed($event->data->object);
                    break;

                default:
                    $this->logger->info('Stripe webhook: Unhandled event type', ['type' => $event->type]);
            }

            return new Response('Webhook handled', Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Stripe webhook: Error handling event', [
                'event_type' => $event->type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 500 so Stripe retries the webhook
            return new Response('Webhook handling failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        $this->logger->info('Stripe: Checkout session completed', ['session_id' => $session->id]);

        $order = $this->orderRepository->findOneBy(['stripeSessionId' => $session->id]);

        if (!$order) {
            $this->logger->error('Order not found for Stripe session ID: ' . $session->id);
            return;
        }
        if ($order->getStatus() === OrderStatus::PAID) {
            $this->logger->info('Stripe: Order already marked as paid', ['order_id' => $order->getId()]);
            return;
        }

        // Retrieve full session to get payment intent
        $fullSession = $this->stripeClient->checkout->sessions->retrieve($session->id);

        // Update order status to paid
        $order->setStatus(OrderStatus::PAID);
        $order->setPaidAt(new \DateTimeImmutable());
        $order->setStripePaymentIntentId($fullSession->payment_intent);
        $this->entityManager->flush();

        try {
            // Decrement stock
            $this->checkoutService->decrementStock($order);

            $this->entityManager->flush();

            $this->logger->info('Stripe: Order marked as paid and stock decremented', [
                'order_id' => $order->getId(),
                'order_reference' => $order->getReference(),
            ]);

            // TODO: Send confirmation email to customer

        } catch (\RuntimeException $e) {
            // Stock insufficient - this should be very rare
            $this->logger->critical('Stripe: Insufficient stock after payment', [
                'order_id' => $order->getId(),
                'error' => $e->getMessage(),
            ]);

            // Mark order for manual review
            $order->setStatus(OrderStatus::PENDING);
            $this->entityManager->flush();

            // TODO: Trigger refund process
            // TODO: Send notification to admin
        }
    }

    /**
     * Handle successful payment intent
     */
    private function handlePaymentIntentSucceeded(object $paymentIntent): void
    {
        $this->logger->info('Stripe: Payment intent succeeded', ['payment_intent_id' => $paymentIntent->id]);
        // Additional handling can be implemented here if needed
    }

    /**
     * Handle failed payment intent
     */
    private function handlePaymentIntentFailed(object $paymentIntent): void
    {
        $this->logger->warning('Stripe: Payment intent failed', ['payment_intent_id' => $paymentIntent->id]);
        // Additional handling can be implemented here if needed
        // TODO: Notify customer about payment failure
    }
}

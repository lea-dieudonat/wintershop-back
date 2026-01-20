<?php

namespace App\Service;

use App\Entity\Order;
use Stripe\StripeClient;
use Stripe\Checkout\Session;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class StripePaymentService
{
    public function __construct(
        private StripeClient $stripeClient,
        private UrlGeneratorInterface $urlGenerator,
        private string $stripePublicKey
    ) {}

    /**
     * Create a Stripe Checkout Session for the given order.
     */
    public function createCheckoutSession(Order $order): Session
    {
        $lineItems = $this->buildLineItems($order);

        $session = $this->stripeClient->checkout->sessions->create([
            'payment_method_types' => ['card'],
            'line_items' => $lineItems,
            'mode' => 'payment',
            'success_url' => $this->urlGenerator->generate(
                'checkout_success',
                ['orderId' => $order->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ) . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $this->urlGenerator->generate(
                'checkout_cancel',
                ['orderId' => $order->getId()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'client_reference_id' => (string)$order->getId(),
            'customer_email' => $order->getUser()->getEmail(),
            'metadata' => [
                'order_id' => $order->getId(),
                'order_reference' => $order->getReference(),
            ],
            'expires_at' => time() + 30 * 60, // Session expires in 30 minutes
        ]);

        return $session;
    }

    /**
     * Build line items array for Stripe Checkout Session from the order.
     * 
     * @return array<int, array<string, mixed>>
     */
    private function buildLineItems(Order $order): array
    {
        $lineItems = [];

        foreach ($order->getItems() as $item) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => $item->getProduct()->getName(),
                        'description' => $item->getProduct()->getDescription(),
                    ],
                    'unit_amount' => $this->convertToStripeAmount($item->getUnitPrice()),
                ],
                'quantity' => $item->getQuantity(),
            ];
        }

        // Add shipping as a line item if applicable
        if (bccomp($order->getShippingCost(), '0.00', 2) > 0) {
            $lineItems[] = [
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => [
                        'name' => 'Frais de livraison - ' . $order->getShippingMethod()->getLabel(),
                    ],
                    'unit_amount' => $this->convertToStripeAmount($order->getShippingCost()),
                ],
                'quantity' => 1,
            ];
        }

        return $lineItems;
    }

    /**
     * Convert decimal amount to Stripe format (cents)
     * ex: '10.99' => 1099
     */
    private function convertToStripeAmount(string $amount): int
    {
        return (int)bcmul($amount, '100', 0);
    }

    /**
     * Convert Stripe amount (cents) to decimal format
     * ex: 1099 => '10.99'
     */
    public function convertFromStripeAmount(int $amount): string
    {
        return bcdiv((string)$amount, '100', 2);
    }

    /**
     * Get the Stripe public key.
     */
    public function getPublicKey(): string
    {
        return $this->stripePublicKey;
    }
}

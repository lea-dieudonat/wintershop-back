<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Address;
use App\Entity\User;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;

class CheckoutService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Converts a Cart into an Order
     * 
     * @throws \RuntimeException if cart is empty or has invalid items
     */
    public function createOrderFromCart(
        Cart $cart,
        Address $shippingAddress,
        Address $billingAddress
    ): Order {
        // Validation
        if ($cart->getItems()->isEmpty()) {
            throw new \RuntimeException('Cannot create order from empty cart');
        }

        // Verify stock availability for all items
        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            if (!$product->isActive()) {
                throw new \RuntimeException(sprintf('Product "%s" is no longer available', $product->getName()));
            }
            if ($product->getStock() < $cartItem->getQuantity()) {
                throw new \RuntimeException(sprintf(
                    'Insufficient stock for product "%s". Available: %d, Requested: %d',
                    $product->getName(),
                    $product->getStock(),
                    $cartItem->getQuantity()
                ));
            }
        }

        // Create Order
        $order = new Order();
        $order->setUser($cart->getUser());
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setStatus(OrderStatus::PENDING);
        $order->setCreatedAt(new \DateTimeImmutable());

        // Convert CartItems to OrderItems
        $totalAmount = '0.00';
        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProduct($cartItem->getProduct());
            $orderItem->setQuantity($cartItem->getQuantity());
            $orderItem->setUnitPrice($cartItem->getProduct()->getPrice()); // Snapshot current price

            // Calculate item total
            $itemTotal = bcmul($orderItem->getUnitPrice(), (string)$orderItem->getQuantity(), 2);
            $orderItem->setTotalPrice($itemTotal);

            $order->addItem($orderItem);
            $totalAmount = bcadd($totalAmount, $itemTotal, 2);
        }

        $order->setTotalAmount($totalAmount);

        // Persist order
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * Clear the cart after successful order creation
     */
    public function clearCart(Cart $cart): void
    {
        foreach ($cart->getItems() as $item) {
            $this->entityManager->remove($item);
        }

        $cart->getItems()->clear();
        $cart->setTotalPrice('0.00');
        $cart->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * Decrement stock when order is marked as paid
     */
    public function decrementStock(Order $order): void
    {
        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $newStock = $product->getStock() - $orderItem->getQuantity();

            if ($newStock < 0) {
                throw new \RuntimeException(sprintf(
                    'Cannot decrement stock for product "%s": insufficient quantity',
                    $product->getName()
                ));
            }

            $product->setStock($newStock);
        }

        $this->entityManager->flush();
    }
}

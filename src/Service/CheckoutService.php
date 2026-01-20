<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Address;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityManagerInterface;
use App\Enum\ShippingMethod;

class CheckoutService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartService $cartService
    ) {}

    /**
     * Converts a Cart into an Order
     *
     * @throws \RuntimeException if cart is empty or has invalid items
     */
    public function createOrderFromCart(
        Cart $cart,
        Address $shippingAddress,
        Address $billingAddress,
        ShippingMethod $shippingMethod
    ): Order {
        $this->validateCartForCheckout($cart);

        // Calculate products total
        $order = $this->createOrder($cart, $shippingAddress, $billingAddress, $shippingMethod);
        $productsTotal = $this->convertCartItemsToOrderItems($cart, $order);

        // Calculate shipping cost
        $shippingCost = $shippingMethod->getActualCost($productsTotal);
        $order->setShippingCost($shippingCost);

        // Calculate final total (product + shipping)
        $totalAmount = bcadd($productsTotal, $shippingCost, 2);
        $order->setTotalAmount($totalAmount);

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return $order;
    }

    /**
     * Validate cart is ready for checkout
     */
    private function validateCartForCheckout(Cart $cart): void
    {
        if ($cart->getItems()->isEmpty()) {
            throw new \RuntimeException('Cannot create order from empty cart');
        }

        // Check for unavailable items WITHOUT removing them
        foreach ($cart->getItems() as $item) {
            $product = $item->getProduct();

            if (!$product || !$product->isActive()) {
                throw new \RuntimeException('Some items in your cart are no longer available. Please review your cart.');
            }

            if ($product->getStock() < $item->getQuantity()) {
                throw new \RuntimeException('Some items in your cart have insufficient stock. Please review your cart.');
            }
        }
    }

    /**
     * Create an order entity from cart data
     */
    private function createOrder(
        Cart $cart,
        Address $shippingAddress,
        Address $billingAddress,
        ShippingMethod $shippingMethod
    ): Order {
        $order = new Order();
        $order->setUser($cart->getUser());
        $order->setShippingAddress($shippingAddress);
        $order->setBillingAddress($billingAddress);
        $order->setShippingMethod($shippingMethod);
        $order->setStatus(OrderStatus::PENDING);
        $order->setCreatedAt(new \DateTimeImmutable());

        return $order;
    }

    /**
     * Convert cart items to order items and calculate total
     *
     * @return string Total amount (products only, without shipping)
     */
    private function convertCartItemsToOrderItems(Cart $cart, Order $order): string
    {
        $totalAmount = '0.00';

        foreach ($cart->getItems() as $cartItem) {
            $orderItem = $this->createOrderItem($cartItem);
            $order->addItem($orderItem);
            $totalAmount = bcadd($totalAmount, $orderItem->getTotalPrice(), 2);
        }

        return $totalAmount;
    }

    /**
     * Create an order item from a cart item
     */
    private function createOrderItem($cartItem): OrderItem
    {
        $orderItem = new OrderItem();
        $orderItem->setProduct($cartItem->getProduct());
        $orderItem->setQuantity($cartItem->getQuantity());
        $orderItem->setUnitPrice($cartItem->getProduct()->getPrice());

        $itemTotal = bcmul($orderItem->getUnitPrice(), (string)$orderItem->getQuantity(), 2);
        $orderItem->setTotalPrice($itemTotal);

        return $orderItem;
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

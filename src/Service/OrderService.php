<?php

namespace App\Service;

use LogicException;
use App\Entity\Order;
use DateTimeImmutable;
use App\Dto\Order\OrderItemOutputDto;
use App\Dto\Order\OrderDetailOutputDto;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

class OrderService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AddressService $addressService,
    ) {}

    /**
     * Convert an Order entity to an OrderDetailOutputDto for detailed view purposes.
     * @param Order $order
     * @return OrderDetailOutputDto
     */
    public function toDetailOutputDto(Order $order): OrderDetailOutputDto
    {
        return new OrderDetailOutputDto(
            id: $order->getId(),
            reference: $order->getReference(),
            status: $order->getStatus()->value,
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt(),
            billingAddress: $this->addressService->toDto($order->getBillingAddress()),
            shippingAddress: $this->addressService->toDto($order->getShippingAddress()),
            items: $this->transformItemsToDto($order->getItems()),
            updatedAt: $order->getUpdatedAt(),
            isCancellable: $this->isCancellable($order),
        );
    }

    /**
     * Cancel an order if it is eligible for cancellation.
     * @param Order $order
     * @param mixed $reason
     * @throws LogicException
     * @return void
     */
    public function cancelOrder(Order $order, ?string $reason = null): void
    {
        if (!$this->isCancellable($order)) {
            throw new LogicException(
                'Cette commande ne peut pas être annulée. ' .
                    'Seules les commandes récentes (moins de 24 heures) peuvent être annulées.'
            );
        }
        $order->cancel();
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $product->restoreStock($item->getQuantity());
        }
        $this->entityManager->flush();
    }

    /**
     * @param Collection $items
     * @return OrderItemOutputDto[]
     */
    private function transformItemsToDto(Collection $items): array
    {
        $itemDtos = [];
        foreach ($items as $item) {
            $itemDtos[] = new OrderItemOutputDto(
                productId: $item->getProduct()->getId(),
                productName: $item->getProductName(),
                productSlug: $item->getProduct()->getSlug(),
                quantity: $item->getQuantity(),
                unitPrice: $item->getUnitPrice(),
                totalPrice: $item->getTotalPrice(),
            );
        }
        return $itemDtos;
    }

    /**
     * Determine if an order can be cancelled.
     * @param Order $order
     * @return bool
     */
    private function isCancellable(Order $order): bool
    {
        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $order->getCreatedAt()->getTimestamp();

        return $order->getStatus()->isCancellable() && $diff < 86400;
    }
}

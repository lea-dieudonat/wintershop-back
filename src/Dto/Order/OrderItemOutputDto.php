<?php

namespace App\Dto\Order;

use App\Entity\OrderItem;

readonly class OrderItemOutputDto
{
    public function __construct(
        public int $productId,
        public string $productName,
        public string $productSlug,
        public int $quantity,
        public string $unitPrice,
        public string $totalPrice,
    ) {}

    public static function fromEntity(OrderItem $orderItem): self
    {
        return new self(
            productId: $orderItem->getProduct()->getId(),
            productName: $orderItem->getProduct()->getName(),
            productSlug: $orderItem->getProduct()->getSlug(),
            quantity: $orderItem->getQuantity(),
            unitPrice: (string) $orderItem->getUnitPrice(),
            totalPrice: (string) $orderItem->getTotalPrice(),
        );
    }
}

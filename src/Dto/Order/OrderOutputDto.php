<?php

namespace App\Dto\Order;

use DateTimeImmutable;

final readonly class OrderOutputDto
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public string $status,
        public string $totalAmount,
        public DateTimeImmutable $createdAt,
        public int $itemCount,
    ) {}

    public static function fromEntity($order): self
    {
        return new self(
            id: $order->getId(),
            orderNumber: $order->getOrderNumber(),
            status: $order->getStatus()->value,
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt(),
            itemCount: $order->getItems()->count(),
        );
    }
}

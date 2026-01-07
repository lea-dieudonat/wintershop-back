<?php

namespace App\Dto\Order;

final readonly class OrderRefundOutputDto
{
    public function __construct(
        public int $orderId,
        public string $status,
        public ?string $refundReason,
        public ?\DateTimeImmutable $refundRequestedAt,
        public string $totalAmount,
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity($order): self
    {
        return new self(
            orderId: $order->getId(),
            status: $order->getStatus()->value,
            refundReason: $order->getRefundReason(),
            refundRequestedAt: $order->getRefundRequestedAt(),
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt(),
        );
    }
}

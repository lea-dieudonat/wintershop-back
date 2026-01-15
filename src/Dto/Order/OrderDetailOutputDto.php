<?php

namespace App\Dto\Order;

use DateTimeImmutable;
use App\Dto\Address\AddressOutputDto;
use App\Entity\Order;

final readonly class OrderDetailOutputDto
{
    /**
     * @param OrderItemOutputDto[] $items
     */
    public function __construct(
        public int $id,
        public string $reference,
        public string $status,
        public string $totalAmount,
        public DateTimeImmutable $createdAt,
        public AddressOutputDto $billingAddress,
        public AddressOutputDto $shippingAddress,
        public array $items,
        public ?DateTimeImmutable $updatedAt = null,
        public bool $isCancellable = false,
    ) {}

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId(),
            reference: $order->getReference(),
            status: $order->getStatus()->value,
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt(),
            billingAddress: AddressOutputDto::fromEntity($order->getBillingAddress()),
            shippingAddress: AddressOutputDto::fromEntity($order->getShippingAddress()),
            items: array_map(
                fn($item) => OrderItemOutputDto::fromEntity($item),
                $order->getItems()->toArray()
            ),
            updatedAt: $order->getUpdatedAt(),
            isCancellable: $order->canRequestCancellation(),
        );
    }
}

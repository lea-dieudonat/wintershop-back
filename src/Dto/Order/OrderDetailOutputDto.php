<?php

namespace App\Dto\Order;

use DateTimeImmutable;
use App\Dto\Address\AddressOutputDto;

readonly class OrderDetailOutputDto
{
    /**
     * @param OrderItemOutputDto[] $items
     */
    public function __construct(
        public int $id,
        public string $orderNumber,
        public string $status,
        public string $totalAmount,
        public DateTimeImmutable $createdAt,
        public AddressOutputDto $billingAddress,
        public AddressOutputDto $shippingAddress,
        public array $items,
        public ?DateTimeImmutable $updatedAt = null,
        public bool $canBeCancelled = false,
    ) {}
}

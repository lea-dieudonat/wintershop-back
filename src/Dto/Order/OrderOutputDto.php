<?php

namespace App\Dto\Order;

use DateTimeImmutable;

readonly class OrderOutputDto
{
    public function __construct(
        public int $id,
        public string $orderNumber,
        public string $status,
        public string $totalAmount,
        public DateTimeImmutable $createdAt,
        public int $itemsCount,
    ) {}
}

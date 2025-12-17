<?php

namespace App\Dto\Cart;

use DateTimeImmutable;

readonly class CartOutputDto
{
    public function __construct(
        public int $id,
        /** @var CartItemOutputDto[] */
        public array $items = [],
        public int $totalItems,
        public string $subtotal,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}

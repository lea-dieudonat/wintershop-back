<?php

namespace App\Dto\Order;

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
}

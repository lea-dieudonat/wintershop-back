<?php

namespace App\Dto\Cart;

use App\Dto\Product\ProductOutputDto;

readonly class CartItemOutputDto
{
    public function __construct(
        public int $id,
        public ProductOutputDto $product,
        public int $quantity,
        public string $unitPrice,
        public string $totalPrice,
    ) {}
}

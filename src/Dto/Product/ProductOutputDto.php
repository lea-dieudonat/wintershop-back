<?php

namespace App\Dto\Product;

readonly class ProductOutputDto
{
    public function __construct(
        public int $id,
        public string $name,
        public string $price,
        public int $stock,
        public ?string $image = null,
    ) {}
}

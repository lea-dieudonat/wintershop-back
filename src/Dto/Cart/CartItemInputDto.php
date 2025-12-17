<?php

namespace App\Dto\Cart;

use Symfony\Component\Validator\Constraints as Assert;

class CartItemInputDto
{
    public function __construct(
        #[Assert\NotBlank(message: "Product ID cannot be blank.")]
        #[Assert\Positive(message: "Product ID must be a positive integer.")]
        public readonly int $productId,

        #[Assert\NotBlank(message: "Quantity cannot be blank.")]
        #[Assert\Positive(message: "Quantity must be a positive integer.")]
        #[Assert\Range(
            min: 1,
            max: 99,
            notInRangeMessage: "Quantity must be between {{ min }} and {{ max }}."
        )]
        public readonly int $quantity,
    ) {}
}

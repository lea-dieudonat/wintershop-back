<?php

namespace App\Dto\Checkout;

use App\Enum\ShippingMethod;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class CheckoutInputDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $shippingAddressId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        public int $billingAddressId,

        #[Assert\NotBlank]
        public ShippingMethod $shippingMethod,
    ) {}
}

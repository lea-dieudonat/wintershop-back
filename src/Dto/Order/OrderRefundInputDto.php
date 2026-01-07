<?php

namespace App\Dto\Order;

use Symfony\Component\Validator\Constraints as Assert;

readonly class OrderRefundInputDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'Refund reason must not be blank.')]
        #[Assert\Length(
            min: 10,
            max: 500,
            minMessage: 'Refund reason must be at least {{ limit }} characters long.',
            maxMessage: 'Refund reason cannot be longer than {{ limit }} characters.'
        )]
        public ?string $reason = null, // TODO: modify to enum later
    ) {}
}

<?php

namespace App\Dto\Order;

use Symfony\Component\Validator\Constraints as Assert;

readonly class OrderRefundInputDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'orders.refund.reasonNotBlank')]
        #[Assert\Length(
            min: 10,
            max: 500,
            minMessage: 'orders.refund.reasonMinLength',
            maxMessage: 'orders.refund.reasonMaxLength'
        )]
        public ?string $reason = null, // TODO: modify to enum later
    ) {}
}

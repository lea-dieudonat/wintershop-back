<?php

namespace App\Dto\Order;

readonly class OrderCancelInputDto
{
    public function __construct(
        public ?string $reason = null,
    ) {}
}

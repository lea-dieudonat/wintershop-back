<?php

namespace App\Dto\Order;

final readonly class OrderCancelInputDto
{
    public function __construct(
        public ?string $reason = null,
    ) {}
}

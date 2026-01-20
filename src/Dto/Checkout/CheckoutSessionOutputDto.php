<?php

namespace App\Dto\Checkout;

final readonly class CheckoutSessionOutputDto
{
    public function __construct(
        public string $sessionId,
        public string $sessionUrl,
        public int $orderId,
        public string $orderReference,
        public string $publicKey,
    ) {}
}

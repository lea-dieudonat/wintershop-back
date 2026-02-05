<?php

namespace App\Dto\Cart;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\State\Cart\CartProvider;
use App\State\Cart\CartProcessor;
use App\State\Cart\CartItemProcessor;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/cart',
            security: "is_granted('ROLE_USER')",
        ),
        new Delete(
            uriTemplate: '/cart',
            security: "is_granted('ROLE_USER')",
            processor: CartProcessor::class,
        ),
        new Post(
            uriTemplate: '/cart/items',
            security: "is_granted('ROLE_USER')",
            input: CartItemInputDto::class,
            processor: CartItemProcessor::class,
        ),
        new Patch(
            uriTemplate: '/cart/items/{id}',
            security: "is_granted('ROLE_USER')",
            input: CartItemInputDto::class,
            processor: CartItemProcessor::class,
            inputFormats: ['json' => ['application/json']]
        ),
        new Delete(
            uriTemplate: '/cart/items/{id}',
            security: "is_granted('ROLE_USER')",
            processor: CartItemProcessor::class
        )
    ],
    provider: CartProvider::class,
)]
readonly class CartOutputDto
{
    public function __construct(
        public int $id,
        /** @var CartItemOutputDto[] */
        public array $items = [],
        public int $totalItems,
        public string $subtotal,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}
}

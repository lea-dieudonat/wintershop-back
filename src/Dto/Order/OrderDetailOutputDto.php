<?php

namespace App\Dto\Order;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Address\AddressOutputDto;
use App\Entity\Order;
use App\State\Order\OrderCollectionProvider;
use App\State\Order\OrderItemProvider;
use App\State\Order\OrderCancellationProcessor;
use App\State\Order\OrderRefundProcessor;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/orders',
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['order:list']],
            provider: OrderCollectionProvider::class
        ),
        new Get(
            uriTemplate: '/orders/{id}',
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['order:detail']],
            provider: OrderItemProvider::class
        ),
        new Patch(
            uriTemplate: '/orders/{id}/cancel',
            security: "is_granted('ROLE_USER')",
            input: OrderCancelInputDto::class,
            processor: OrderCancellationProcessor::class,
            provider: OrderItemProvider::class,
            inputFormats: ['json' => ['application/json']]
        ),
        new Post(
            uriTemplate: '/orders/{id}/refund',
            security: "is_granted('ROLE_USER')",
            input: OrderRefundInputDto::class,
            processor: OrderRefundProcessor::class,
            provider: OrderItemProvider::class
        )
    ],
)]
final readonly class OrderDetailOutputDto
{
    /**
     * @param OrderItemOutputDto[] $items
     */
    public function __construct(
        public int $id,
        public string $reference,
        public string $status,
        public string $totalAmount,
        public DateTimeImmutable $createdAt,
        public AddressOutputDto $billingAddress,
        public AddressOutputDto $shippingAddress,
        public array $items,
        public ?DateTimeImmutable $updatedAt = null,
        public bool $isCancellable = false,
    ) {}

    public static function fromEntity(Order $order): self
    {
        return new self(
            id: $order->getId(),
            reference: $order->getReference(),
            status: $order->getStatus()->value,
            totalAmount: $order->getTotalAmount(),
            createdAt: $order->getCreatedAt(),
            billingAddress: AddressOutputDto::fromEntity($order->getBillingAddress()),
            shippingAddress: AddressOutputDto::fromEntity($order->getShippingAddress()),
            items: array_map(
                fn($item) => OrderItemOutputDto::fromEntity($item),
                $order->getItems()->toArray()
            ),
            updatedAt: $order->getUpdatedAt(),
            isCancellable: $order->canRequestCancellation(),
        );
    }
}

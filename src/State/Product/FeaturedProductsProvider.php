<?php

namespace App\State\Product;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ProductRepository;

/**
 * State Provider pour récupérer les produits mis en avant (featured)
 */
class FeaturedProductsProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        return $this->productRepository->findBy(
            [
                'isFeatured' => true,
                'isActive' => true,
            ],
            ['createdAt' => 'DESC'],
            4
        );
    }
}

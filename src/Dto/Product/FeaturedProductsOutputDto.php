<?php

namespace App\Dto\Product;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\State\FeaturedProductsProvider;
use Symfony\Component\Serializer\Annotation\Groups;


#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/featured-products',
            provider: FeaturedProductsProvider::class,
            normalizationContext: ['groups' => ['product:read']]
        )
    ]
)]
class FeaturedProductsOutputDto
{
    /**
     * Liste des produits mis en avant
     * @var array
     */
    #[Groups(['product:read'])]
    public array $products = [];
}

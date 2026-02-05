<?php

namespace App\Dto;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Delete;
use App\State\User\AddToWishlistProcessor;
use App\State\User\RemoveFromWishlistProcessor;
use App\State\User\UserWishlistProvider;

/**
 * DTO pour gérer la wishlist utilisateur
 * 
 * Endpoints:
 * - GET /api/wishlist - Récupère la wishlist de l'utilisateur connecté
 * - POST /api/wishlist/{productId} - Ajoute un produit à la wishlist
 * - DELETE /api/wishlist/{productId} - Retire un produit de la wishlist
 */
#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/wishlist',
            provider: UserWishlistProvider::class,
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['product:read']]
        ),
        new Post(
            uriTemplate: '/wishlist/{productId}',
            processor: AddToWishlistProcessor::class,
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['product:read']]
        ),
        new Delete(
            uriTemplate: '/wishlist/{productId}',
            processor: RemoveFromWishlistProcessor::class,
            security: "is_granted('ROLE_USER')",
            normalizationContext: ['groups' => ['product:read']]
        ),
    ]
)]
class WishlistOutputDto
{
    // Ce DTO sert uniquement à définir les endpoints
    // Les processors/providers retournent directement les produits
}

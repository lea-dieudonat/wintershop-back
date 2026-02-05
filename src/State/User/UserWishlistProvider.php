<?php

namespace App\State\User;

use App\Entity\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * State Provider pour récupérer la wishlist de l'utilisateur connecté
 */
class UserWishlistProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return [];
        }

        return $user->getWishlist()->toArray();
    }
}

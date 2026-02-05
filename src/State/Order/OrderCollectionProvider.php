<?php

namespace App\State\Order;

use App\Entity\Order;
use App\Repository\OrderRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

class OrderCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRepository $orderRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        // Fetch only orders belonging to the authenticated user
        return $this->orderRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }
}

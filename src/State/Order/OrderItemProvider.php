<?php

namespace App\State\Order;

use App\Entity\Order;
use App\Repository\OrderRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrderItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRepository $orderRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            return null;
        }

        $orderId = $uriVariables['id'] ?? null;
        if (!$orderId) {
            return null;
        }

        $order = $this->orderRepository->find($orderId);

        // If order doesn't exist, return null (404)
        if (!$order) {
            return null;
        }

        // If order exists but doesn't belong to the user, throw 403
        if ($order->getUser() !== $user) {
            throw new AccessDeniedHttpException('You are not allowed to access this order.');
        }

        return $order;
    }
}

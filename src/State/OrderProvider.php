<?php

namespace App\State;

use App\Dto\Order\OrderOutputDto;
use ApiPlatform\Metadata\Operation;
use App\Repository\OrderRepository;
use App\Dto\Order\OrderDetailOutputDto;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\Metadata\Exception\AccessDeniedException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OrderProvider implements ProviderInterface
{
    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedException('User not authenticated');
        }

        // Collection : GET /api/orders
        if (empty($uriVariables)) {
            return $this->provideCollection($user);
        }

        // Item : GET /api/orders/{id}
        return $this->provideItem($uriVariables['id'], $user);
    }

    public function provideCollection(object $user): array
    {
        $orders = $this->orderRepository->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return array_map(
            fn($order) => OrderOutputDto::fromEntity($order),
            $orders
        );
    }

    public function provideItem(int $orderId, object $user): OrderDetailOutputDto
    {
        $order = $this->orderRepository->find($orderId);

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        if ($order->getUser() !== $user) {
            throw new AccessDeniedException('You do not have access to this order');
        }

        return OrderDetailOutputDto::fromEntity($order);
    }
}

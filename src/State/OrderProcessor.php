<?php

namespace App\State;

use DateTimeImmutable;
use App\Enum\OrderStatus;
use Psr\Log\LoggerInterface;
use ApiPlatform\Metadata\Operation;
use App\Repository\OrderRepository;
use App\Dto\Order\OrderDetailOutputDto;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class OrderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly OrderRepository $orderRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderDetailOutputDto
    {
        $user = $this->security->getUser();

        if (!$user) {
            throw new AccessDeniedHttpException('User not found');
        }

        $orderId = $uriVariables['id'] ?? null;

        if (!$orderId) {
            throw new BadRequestHttpException('Order ID not provided');
        }

        $order = $this->orderRepository->find($orderId);
        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // Check if the order belongs to the authenticated user
        if ($order->getUser() !== $user) {
            throw new AccessDeniedHttpException('You are not allowed to cancel this order');
        }

        // Check if the order is in a cancellable state
        if (!$order->isCancellable()) {
            throw new BadRequestHttpException('Order cannot be cancelled in its current state');
        }

        // Perform the cancellation
        $order->setStatus(OrderStatus::CANCELLED);
        $order->setUpdatedAt(new DateTimeImmutable());
        if (isset($data->reason)) {
            $this->logger->info('Order cancellation', [
                'orderId' => $order->getId(),
                'reason' => $data->reason,
            ]); // TODO: register reason properly
        }
        $this->entityManager->flush();

        // Return the updated order as a DTO
        return OrderDetailOutputDto::fromEntity($order);
    }
}

<?php

namespace App\State;

use App\Entity\Order;
use DateTimeImmutable;
use App\Enum\OrderStatus;
use ApiPlatform\Metadata\Operation;
use App\Dto\Order\OrderRefundOutputDto;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Exception\OrderNotRefundableException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class OrderRefundProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {}
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): OrderRefundOutputDto
    {
        $orderId = $uriVariables['id'] ?? null;

        if (!$orderId) {
            throw new BadRequestHttpException('Order ID not provided');
        }

        $order = $this->entityManager->getRepository(Order::class)->find($orderId);

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        $currentUser = $this->security->getUser();

        // Check if the order belongs to the authenticated user
        if (!$currentUser || $order->getUser() !== $currentUser) {
            throw new AccessDeniedHttpException('You are not allowed to refund this order');
        }

        // Check if the order is in a refundable state
        try {
            $order->assertCanRequestRefund();
        } catch (OrderNotRefundableException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }

        // Perform the refund
        $order->setStatus(OrderStatus::REFUND_REQUESTED);
        $order->setRefundReason($data->reason ?? null);
        $order->setRefundRequestedAt(new DateTimeImmutable());
        $order->setUpdatedAt(new DateTimeImmutable());
        $this->entityManager->flush();

        return OrderRefundOutputDto::fromEntity($order);
    }
}

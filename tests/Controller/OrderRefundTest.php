<?php

namespace App\Tests\Functional;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\AddressFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class OrderRefundTest extends WebTestCase
{
    private const REFUND_URI = '/api/orders/%d/refund';

    private KernelBrowser $client;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->manager = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testRefundOrderSuccessfully(): void
    {
        // Créer et connecter un utilisateur
        $user = UserFactory::createOne([
            'email' => 'refund-user@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        // Créer une commande livrée il y a 5 jours
        $order = $this->createDeliveredOrder($user, '-5 days');

        // Faire la demande de remboursement
        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Le produit ne correspond pas à la description',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('orderId', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('refundReason', $data);
        $this->assertArrayHasKey('refundRequestedAt', $data);
        $this->assertEquals('refund_requested', $data['status']);
        $this->assertEquals('Le produit ne correspond pas à la description', $data['refundReason']);
    }

    public function testRefundOrderNotFound(): void
    {
        $user = UserFactory::createOne([
            'email' => 'user-notfound@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, 99999),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Test reason that is long enough',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRefundOrderFromAnotherUser(): void
    {
        // Créer deux utilisateurs
        $owner = UserFactory::createOne([
            'email' => 'owner@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $otherUser = UserFactory::createOne([
            'email' => 'other@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        // Se connecter avec l'autre utilisateur
        $this->client->loginUser($otherUser);

        // Créer une commande pour le propriétaire
        $order = $this->createDeliveredOrder($owner, '-5 days');

        // Essayer de rembourser
        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Test reason that is long enough',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRefundOrderNotDelivered(): void
    {
        $user = UserFactory::createOne([
            'email' => 'pending-order@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        // Créer une commande PENDING (pas livrée)
        $order = $this->createOrder($user, OrderStatus::PENDING);

        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Test reason that is long enough',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('delivered', strtolower($data['detail']));
    }

    public function testRefundOrderDeadlineExpired(): void
    {
        $user = UserFactory::createOne([
            'email' => 'expired@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        // Créer une commande livrée il y a 20 jours (> 14 jours)
        $order = $this->createDeliveredOrder($user, '-20 days');

        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Test reason that is long enough',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('expired', strtolower($data['detail']));
    }

    public function testRefundOrderAlreadyRequested(): void
    {
        $user = UserFactory::createOne([
            'email' => 'already-requested@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        // Créer une commande avec demande de remboursement existante
        $order = $this->createRefundRequestedOrder($user);

        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Another reason that is long enough',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already', strtolower($data['detail']));
    }

    public function testRefundOrderWithoutReason(): void
    {
        $user = UserFactory::createOne([
            'email' => 'no-reason@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        $order = $this->createDeliveredOrder($user, '-5 days');

        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRefundOrderWithShortReason(): void
    {
        $user = UserFactory::createOne([
            'email' => 'short-reason@test.com',
            'roles' => ['ROLE_USER'],
        ])->_real();

        $this->client->loginUser($user);

        $order = $this->createDeliveredOrder($user, '-5 days');

        $this->client->request(
            'POST',
            sprintf(self::REFUND_URI, $order->getId()),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'reason' => 'Nul', // Moins de 10 caractères
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    private function createOrder($user, OrderStatus $status): Order
    {
        $address = AddressFactory::createOne([
            'user' => $user,
        ])->_real();

        $order = new Order();
        $order->setUser($user);
        $order->setStatus($status);
        $order->setTotalAmount('99.99');
        $order->setShippingAddress($address);
        $order->setBillingAddress($address);
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setUpdatedAt(new \DateTimeImmutable());

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function createDeliveredOrder($user, string $deliveredAgo): Order
    {
        $address = AddressFactory::createOne([
            'user' => $user,
        ])->_real();

        $order = new Order();
        $order->setUser($user);
        $order->setStatus(OrderStatus::DELIVERED);
        $order->setTotalAmount('99.99');
        $order->setShippingAddress($address);
        $order->setBillingAddress($address);
        $order->setCreatedAt(new \DateTimeImmutable('-30 days'));
        $order->setDeliveredAt(new \DateTimeImmutable($deliveredAgo));
        $order->setUpdatedAt(new \DateTimeImmutable());

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }

    private function createRefundRequestedOrder($user): Order
    {
        $address = AddressFactory::createOne([
            'user' => $user,
        ])->_real();

        $order = new Order();
        $order->setUser($user);
        $order->setStatus(OrderStatus::REFUND_REQUESTED);
        $order->setTotalAmount('99.99');
        $order->setShippingAddress($address);
        $order->setBillingAddress($address);
        $order->setCreatedAt(new \DateTimeImmutable('-30 days'));
        $order->setDeliveredAt(new \DateTimeImmutable('-5 days'));
        $order->setRefundReason('Produit défectueux');
        $order->setRefundRequestedAt(new \DateTimeImmutable('-2 days'));
        $order->setUpdatedAt(new \DateTimeImmutable());

        $this->manager->persist($order);
        $this->manager->flush();

        return $order;
    }
}

<?php

namespace App\DataFixtures;

use App\Entity\User;
use App\Entity\Order;
use DateTimeImmutable;
use App\Entity\Address;
use App\Entity\Product;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $users = $manager->getRepository(User::class)->findAll();
        $products = $manager->getRepository(Product::class)->findAll();

        if (empty($users) || empty($products)) {
            return;
        }

        for ($i = 1; $i <= 30; $i++) {
            $user = $users[array_rand($users)];

            // Récupérer les adresses existantes de l'utilisateur
            $userAddresses = $manager->getRepository(Address::class)->findBy(['user' => $user]);

            if (empty($userAddresses)) {
                continue; // Pas d'adresses pour cet user, on passe
            }

            // Choisir des adresses aléatoires parmi celles de l'utilisateur
            $shippingAddress = $userAddresses[array_rand($userAddresses)];
            $billingAddress = $userAddresses[array_rand($userAddresses)];

            $order = new Order();
            $order->setUser($user);
            $order->setShippingAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);

            // Date de création aléatoire dans les 90 derniers jours
            $daysAgo = rand(0, 90);
            $createdAt = new \DateTimeImmutable("-$daysAgo days");
            $order->setCreatedAt($createdAt);

            // Répartir les statuts de manière variée
            $statusRandom = rand(1, 100);
            $status = match (true) {
                $statusRandom <= 10 => OrderStatus::PENDING,           // 10% en attente
                $statusRandom <= 20 => OrderStatus::PAID,              // 10% payées
                $statusRandom <= 30 => OrderStatus::PROCESSING,        // 10% en préparation
                $statusRandom <= 45 => OrderStatus::SHIPPED,           // 15% expédiées
                $statusRandom <= 80 => OrderStatus::DELIVERED,         // 35% livrées
                $statusRandom <= 85 => OrderStatus::CANCELLED,         // 5% annulées
                $statusRandom <= 90 => OrderStatus::REFUND_REQUESTED,  // 5% remboursement demandé
                default => OrderStatus::REFUNDED,                      // 10% remboursées
            };
            $order->setStatus($status);

            // Gestion des dates en fonction du statut
            $this->setOrderDates($order, $status, $createdAt);

            $totalAmount = '0.00';

            // Ajouter entre 1 et 4 produits à la commande
            $itemCount = rand(1, 4);
            for ($j = 0; $j < $itemCount; $j++) {
                $product = $products[array_rand($products)];
                $quantity = rand(1, 3);

                $orderItem = new OrderItem();
                $orderItem->setParentOrder($order);
                $orderItem->setProduct($product);
                $orderItem->setQuantity($quantity);
                $orderItem->setUnitPrice($product->getPrice());

                // Utiliser bcmath pour les calculs de prix
                $totalItem = bcmul($product->getPrice(), (string)$quantity, 2);
                $orderItem->setTotalPrice($totalItem);

                $totalAmount = bcadd($totalAmount, $totalItem, 2);

                $manager->persist($orderItem);
            }

            $order->setTotalAmount($totalAmount);
            $manager->persist($order);
        }

        $manager->flush();
    }

    /**
     * Définit les dates appropriées en fonction du statut de la commande.
     */
    private function setOrderDates(Order $order, OrderStatus $status, DateTimeImmutable $createdAt): void
    {
        switch ($status) {
            case OrderStatus::PENDING:
            case OrderStatus::CANCELLED:
                // Pas de date de livraison
                break;

            case OrderStatus::PAID:
                // Payée récemment (derniers jours)
                $order->setUpdatedAt($createdAt->modify('+' . rand(1, 2) . ' hours'));
                break;

            case OrderStatus::PROCESSING:
                // En préparation (1-2 jours après création)
                $order->setUpdatedAt($createdAt->modify('+' . rand(1, 2) . ' days'));
                break;

            case OrderStatus::SHIPPED:
                // Expédiée (2-4 jours après création)
                $order->setUpdatedAt($createdAt->modify('+' . rand(2, 4) . ' days'));
                break;

            case OrderStatus::DELIVERED:
                // Livrée (3-7 jours après création)
                $deliveredAt = $createdAt->modify('+' . rand(3, 7) . ' days');
                $order->setDeliveredAt($deliveredAt);
                $order->setUpdatedAt($deliveredAt);
                break;

            case OrderStatus::REFUND_REQUESTED:
                // Demande de remboursement après livraison
                $deliveredAt = $createdAt->modify('+' . rand(3, 7) . ' days');
                $order->setDeliveredAt($deliveredAt);
                // Demande faite quelques jours après livraison
                $order->setUpdatedAt($deliveredAt->modify('+' . rand(1, 5) . ' days'));
                break;

            case OrderStatus::REFUNDED:
                // Remboursée après livraison
                $deliveredAt = $createdAt->modify('+' . rand(3, 7) . ' days');
                $order->setDeliveredAt($deliveredAt);
                // Remboursement traité quelques jours après demande
                $order->setUpdatedAt($deliveredAt->modify('+' . rand(5, 10) . ' days'));
                break;
        }
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProductFixtures::class,
            AddressFixtures::class,
        ];
    }
}

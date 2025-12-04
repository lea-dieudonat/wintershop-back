<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\User;
use App\Entity\Product;
use App\Entity\Address;
use App\Enum\OrderStatus;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $users = $manager->getRepository(User::class)->findAll();
        $products = $manager->getRepository(Product::class)->findAll();

        if (empty($users) || empty($products)) {
            return;
        }

        for ($i = 1; $i <= 10; $i++) {
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

            $status = match ($i % 5) {
                0 => OrderStatus::PENDING,
                1 => OrderStatus::PAID,
                2 => OrderStatus::SHIPPED,
                3 => OrderStatus::DELIVERED,
                4 => OrderStatus::CANCELLED,
            };
            $order->setStatus($status);

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

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProductFixtures::class,
            AddressFixtures::class, // Nouvelle dépendance !
        ];
    }
}

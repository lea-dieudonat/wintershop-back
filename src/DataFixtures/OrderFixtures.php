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
        // Récupérer des users et produits existants
        $users = $manager->getRepository(User::class)->findAll();
        $products = $manager->getRepository(Product::class)->findAll();

        if (empty($users) || empty($products)) {
            return; // Pas de données pour créer des commandes
        }

        for ($i = 1; $i <= 10; $i++) {
            $user = $users[array_rand($users)];

            // Créer les adresses
            $shippingAddress = $this->createAddress($user, $i, $manager);
            $billingAddress = $this->createAddress($user, $i, $manager);

            $order = new Order();
            $order->setUser($user);
            $order->setShippingAddress($shippingAddress);
            $order->setBillingAddress($billingAddress);

            $status = match($i % 5) {
                0 => OrderStatus::PENDING,
                1 => OrderStatus::PAID,
                2 => OrderStatus::SHIPPED,
                3 => OrderStatus::DELIVERED,
                4 => OrderStatus::CANCELLED,
            };
            $order->setStatus($status);

            $totalAmount = 0;

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

                $totalItem = $product->getPrice() * $quantity;
                $orderItem->setTotalPrice($totalItem);

                $totalAmount += $totalItem;

                $manager->persist($orderItem);
            }
            $order->setTotalAmount($totalAmount);
            $manager->persist($order);
        }
        $manager->flush();
    }

    private function createAddress(User $user, int $orderNumber, ObjectManager $manager): Address
    {
        $address = new Address();
        $address->setFirstName($user->getFirstName());
        $address->setLastName($user->getLastName());
        $address->setStreet($orderNumber . ' rue Sésame');
        $address->setCity('Chamonix');
        $address->setPostalCode('74400');
        $address->setCountry('France');
        $address->setPhoneNumber('0123456789');
        $address->setUser($user);

        $manager->persist($address);

        return $address;
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProductFixtures::class,
        ];
    }
}

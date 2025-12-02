<?php

namespace App\DataFixtures;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CartFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Récupérer quelques utilisateurs et produits existants via les repositories
        $userRepo = $manager->getRepository(User::class);
        $productRepo = $manager->getRepository(Product::class);

        $users = $userRepo->findBy([], null, 3);
        $products = $productRepo->findAll();

        if (count($users) === 0 || count($products) === 0) {
            return; // Pas d'utilisateurs ou de produits à utiliser
        }

        // Créer un panier avec quelques articles pour le premier utilisateur
        $user1 = $users[0];
        $cart1 = new Cart();
        $cart1->setUser($user1);
        $manager->persist($cart1);

        // Ajouter 3 produits aléatoires au panier
        $selectedProducts = array_rand($products, min(3, count($products)));
        if (!is_array($selectedProducts)) {
            $selectedProducts = [$selectedProducts];
        }

        foreach ($selectedProducts as $index) {
            $product = $products[$index];
            
            $cartItem = new CartItem();
            $cartItem->setCart($cart1);
            $cartItem->setProduct($product);
            $cartItem->setQuantity(rand(1, 3));
            $cartItem->setUnitPrice($product->getPrice());
            
            $manager->persist($cartItem);
        }

        // Créer un panier avec un seul article pour le deuxième utilisateur (si disponible)
        if (count($users) > 1) {
            $user2 = $users[1];
            $cart2 = new Cart();
            $cart2->setUser($user2);
            $manager->persist($cart2);

            $product = $products[array_rand($products)];
            
            $cartItem = new CartItem();
            $cartItem->setCart($cart2);
            $cartItem->setProduct($product);
            $cartItem->setQuantity(1);
            $cartItem->setUnitPrice($product->getPrice());
            
            $manager->persist($cartItem);
        }

        // Créer un panier vide pour le troisième utilisateur (si disponible)
        if (count($users) > 2) {
            $user3 = $users[2];
            $cart3 = new Cart();
            $cart3->setUser($user3);
            $manager->persist($cart3);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProductFixtures::class,
        ];
    }
}
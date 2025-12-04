<?php

namespace App\DataFixtures;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AddressFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR'); // Locale française pour des données réalistes

        $users = $manager->getRepository(User::class)->findAll();

        if (empty($users)) {
            return;
        }

        foreach ($users as $user) {
            // Créer 2-3 adresses par utilisateur
            $addressCount = rand(2, 3);

            for ($i = 0; $i < $addressCount; $i++) {
                $address = new Address();
                $address->setFirstName($user->getFirstName());
                $address->setLastName($user->getLastName());
                $address->setStreet($faker->streetAddress());
                $address->setAdditionalInfo($i === 0 ? null : $faker->optional(0.3)->secondaryAddress()); // 30% de chance d'avoir un complément
                $address->setCity($faker->city());
                $address->setPostalCode($faker->postcode());
                $address->setCountry('France');
                $address->setPhoneNumber($faker->phoneNumber());
                $address->setUser($user);

                // La première adresse est marquée par défaut
                $address->setIsDefault($i === 0);

                $manager->persist($address);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}

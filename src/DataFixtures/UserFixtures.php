<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Create an admin user
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setCreatedAt(new \DateTimeImmutable());
        $admin->setPassword(
            $this->passwordHasher->hashPassword(
                $admin,
                'adminpassword'
            )
        );
        $manager->persist($admin);
        $this->addReference('user_admin', $admin);

        // Create a regular user
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setFirstName('Jane');
        $user->setLastName('Doe');
        $user->setRoles(['ROLE_USER']);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                'userpassword'
            )
        );
        $manager->persist($user);
        $this->addReference('user_regular', $user);

        $manager->flush();
    }
}

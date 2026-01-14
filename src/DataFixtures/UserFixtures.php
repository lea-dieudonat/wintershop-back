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
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Create admin users (data-driven)
        $adminUsers = [
            ['email' => 'admin@example.com', 'firstName' => 'Admin', 'lastName' => 'User'],
            ['email' => 'admin@wintershop.com', 'firstName' => 'Super', 'lastName' => 'Admin'],
        ];

        foreach ($adminUsers as $idx => $data) {
            $this->createUser(
                $manager,
                $data['email'],
                $data['firstName'],
                $data['lastName'],
                ['ROLE_ADMIN'],
                'adminpassword',
                'user_admin_' . ($idx + 1)
            );
        }

        // Create multiple regular users (data-driven)
        $regularUsers = [
            ['email' => 'user1@example.com', 'firstName' => 'Jane', 'lastName' => 'Doe'],
            ['email' => 'user2@example.com', 'firstName' => 'John', 'lastName' => 'Smith'],
            ['email' => 'user3@example.com', 'firstName' => 'Alice', 'lastName' => 'Brown'],
            ['email' => 'test@wintershop.com', 'firstName' => 'John', 'lastName' => 'Doe'],
        ];

        foreach ($regularUsers as $idx => $data) {
            $this->createUser(
                $manager,
                $data['email'],
                $data['firstName'],
                $data['lastName'],
                ['ROLE_USER'],
                'password123',
                'user_regular_' . ($idx + 1)
            );
        }

        $manager->flush();
    }

    /**
     * Helper to create and persist a User and optionally add a reference.
     */
    private function createUser(
        ObjectManager $manager,
        string $email,
        string $firstName,
        string $lastName,
        array $roles,
        string $plainPassword,
        ?string $reference = null
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setRoles($roles);
        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                $user,
                $plainPassword
            )
        );

        $manager->persist($user);

        if ($reference) {
            $this->addReference($reference, $user);
        }

        return $user;
    }
}

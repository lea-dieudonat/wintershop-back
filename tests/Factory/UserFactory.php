<?php

namespace App\Tests\Factory;

use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->email(),
            'firstName' => self::faker()->firstName(),
            'lastName' => self::faker()->lastName(),
            'password' => '$2y$13$placeholder', // Plain placeholder, should be hashed
            'roles' => ['ROLE_USER'],
        ];
    }

    public function admin(): self
    {
        return $this->with([
            'roles' => ['ROLE_ADMIN'],
        ]);
    }
}

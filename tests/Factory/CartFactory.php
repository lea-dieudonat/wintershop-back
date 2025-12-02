<?php

namespace App\Tests\Factory;

use App\Entity\Cart;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Cart>
 */
final class CartFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Cart::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
        ];
    }
}

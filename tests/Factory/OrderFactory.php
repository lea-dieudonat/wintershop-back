<?php

namespace App\Tests\Factory;

use App\Entity\Order;
use App\Enum\OrderStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Order>
 */
final class OrderFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return Order::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'shippingAddress' => AddressFactory::new(),
            'billingAddress' => AddressFactory::new(),
            'status' => OrderStatus::PENDING,
            'totalAmount' => (string) self::faker()->randomFloat(2, 10, 1000),
        ];
    }
}

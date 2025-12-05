<?php

namespace App\Tests\Factory;

use App\Entity\OrderItem;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<OrderItem>
 */
final class OrderItemFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return OrderItem::class;
    }

    protected function defaults(): array
    {
        $quantity = self::faker()->numberBetween(1, 10);
        $unitPrice = (string) self::faker()->randomFloat(2, 5, 500);
        $totalPrice = bcmul($unitPrice, (string) $quantity, 2);

        return [
            'parentOrder' => OrderFactory::new(),
            'product' => ProductFactory::new(),
            'quantity' => $quantity,
            'unitPrice' => $unitPrice,
            'totalPrice' => $totalPrice,
        ];
    }
}

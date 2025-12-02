<?php

namespace App\Tests\Factory;

use App\Entity\CartItem;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<CartItem>
 */
final class CartItemFactory extends PersistentProxyObjectFactory
{
    public static function class(): string
    {
        return CartItem::class;
    }

    protected function defaults(): array
    {
        return [
            'cart' => CartFactory::new(),
            'product' => ProductFactory::new(),
            'quantity' => self::faker()->numberBetween(1, 5),
            // unitPrice will be initialized from the product price in initialize()
            'unitPrice' => self::faker()->randomFloat(2, 10, 100),
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (CartItem $cartItem): void {
            $product = $cartItem->getProduct();
            if ($product) {
                // Ensure unitPrice matches the product price by default
                $cartItem->setUnitPrice((string) $product->getPrice());
            }
        });
    }
}

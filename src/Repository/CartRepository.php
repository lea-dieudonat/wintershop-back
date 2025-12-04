<?php

namespace App\Repository;

use App\Entity\Cart;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class CartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function recalculateTotal(Cart $cart): string
    {
        $total = '0.00';
        foreach ($cart->getItems() as $item) {
            $unit = $item->getUnitPrice() ?? $item->getProduct()?->getPrice() ?? '0.00';
            $line = bcmul((string) $unit, (string) $item->getQuantity(), 2);
            $total = bcadd($total, $line, 2);
        }

        $cart->setTotalPrice($total);

        return $total;
    }

    /**
     * Remove unavailable items from cart and return info about removed items
     *
     * @return array Array of unavailable items info
     */
    public function removeUnavailableItems(Cart $cart): array
    {
        $unavailableItems = [];
        $itemsToRemove = [];

        foreach ($cart->getItems()->toArray() as $item) {
            $product = $item->getProduct();

            if (!$product || !$product->isActive() || $product->getStock() < $item->getQuantity()) {
                $unavailableItems[] = [
                    'name' => $product ? $product->getName() : 'Unknown',
                    'reason' => !$product ? 'Product not found' : (!$product->isActive() ? 'Produit plus disponible' : 'Stock insuffisant'),
                    'requestedQty' => $item->getQuantity(),
                    'availableStock' => $product ? $product->getStock() : 0,
                ];

                $itemsToRemove[] = $item;
            }
        }

        if (!empty($itemsToRemove)) {
            $em = $this->getEntityManager();
            foreach ($itemsToRemove as $item) {
                $cart->removeItem($item);
                $em->remove($item);
            }

            $this->recalculateTotal($cart);
            $cart->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
        }

        return $unavailableItems;
    }
}

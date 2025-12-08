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

    /**
     * Delete all cart items for a given cart using DQL
     */
    public function clearAllItems(Cart $cart): void
    {
        $this->getEntityManager()
            ->createQuery('DELETE FROM App\\Entity\\CartItem ci WHERE ci.cart = :cart')
            ->setParameter('cart', $cart)
            ->execute();
    }

    /**
     * Remove specific cart items from the database
     *
     * @param array $items Array of CartItem entities to remove
     */
    public function removeItems(Cart $cart, array $items): void
    {
        $em = $this->getEntityManager();
        foreach ($items as $item) {
            $cart->removeItem($item);
            $em->remove($item);
        }
        $em->flush();
    }

    /**
     * Find a cart item by product in the given cart
     */
    public function findItemByProduct(Cart $cart, $product)
    {
        foreach ($cart->getItems() as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                return $item;
            }
        }

        return null;
    }
}

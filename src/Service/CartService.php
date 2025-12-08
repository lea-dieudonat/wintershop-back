<?php

namespace App\Service;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Product;
use App\Entity\User;
use App\Repository\CartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use InvalidArgumentException;
use RuntimeException;
use DateTimeImmutable;

class CartService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CartRepository $cartRepository,
        private Security $security,
    ) {}

    /**
     * Add a product to the cart with the specified quantity
     *
     * @throws InvalidArgumentException If quantity is invalid or stock is insufficient
     */
    public function addProduct(Cart $cart, Product $product, int $quantity): void
    {
        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($product->getStock() < $quantity) {
            throw new InvalidArgumentException('Not enough stock available.');
        }

        $cartItem = $this->cartRepository->findItemByProduct($cart, $product);

        if ($cartItem) {
            $newQuantity = $cartItem->getQuantity() + $quantity;

            if ($product->getStock() < $newQuantity) {
                throw new InvalidArgumentException('Not enough stock available to add that quantity.');
            }

            $cartItem->setQuantity($newQuantity);
        } else {
            $cartItem = new CartItem();
            $cartItem->setProduct($product);
            $cartItem->setQuantity($quantity);
            $cartItem->setUnitPrice((string) $product->getPrice());
            $cart->addItem($cartItem);
            $this->entityManager->persist($cartItem);
        }

        $this->calculateTotal($cart);
        $this->entityManager->flush();
    }

    /**
     * Update the quantity of a cart item
     *
     * @throws InvalidArgumentException If quantity is invalid or stock is insufficient
     * @throws AccessDeniedException If cart item doesn't belong to the cart
     */
    public function updateQuantity(Cart $cart, CartItem $cartItem, int $quantity): void
    {
        if ($cartItem->getCart()->getId() !== $cart->getId()) {
            throw new AccessDeniedException('You do not have permission to modify this cart item.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Quantity must be at least 1.');
        }

        if ($cartItem->getProduct()->getStock() < $quantity) {
            throw new InvalidArgumentException('Not enough stock available.');
        }

        $cartItem->setQuantity($quantity);
        $this->calculateTotal($cart);
        $this->entityManager->flush();
    }

    /**
     * Remove a cart item from the cart
     *
     * @throws AccessDeniedException If cart item doesn't belong to the cart
     */
    public function removeProduct(Cart $cart, CartItem $cartItem): void
    {
        if ($cartItem->getCart()->getId() !== $cart->getId()) {
            throw new AccessDeniedException('You do not have permission to modify this cart item.');
        }

        $cart->removeItem($cartItem);
        $this->entityManager->remove($cartItem);
        $this->calculateTotal($cart);
        $this->entityManager->flush();
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

            if ($this->isItemUnavailable($item, $product)) {
                $unavailableItems[] = $this->createUnavailableItemInfo($item, $product);
                $itemsToRemove[] = $item;
            }
        }

        if (!empty($itemsToRemove)) {
            $this->cartRepository->removeItems($cart, $itemsToRemove);
            $this->calculateTotal($cart);
            $this->entityManager->flush();
        }

        return $unavailableItems;
    }

    private function isItemUnavailable(CartItem $item, Product $product): bool
    {
        return !$product || !$product->isActive() || $product->getStock() < $item->getQuantity();
    }

    private function createUnavailableItemInfo(CartItem $item, Product $product): array
    {
        return [
            'name' => $product?->getName() ?? 'Unknown',
            'reason' => $this->getUnavailableReason($product, $item),
            'requestedQty' => $item->getQuantity(),
            'availableStock' => $product?->getStock() ?? 0,
        ];
    }

    private function getUnavailableReason(Product $product): string
    {
        if (!$product) {
            return 'Product not found';
        }

        if (!$product->isActive()) {
            return 'Produit plus disponible';
        }

        return 'Stock insuffisant';
    }

    public function calculateTotal(Cart $cart): string
    {
        $total = '0.00';
        foreach ($cart->getItems() as $item) {
            $unit = $item->getUnitPrice() ?? $item->getProduct()?->getPrice() ?? '0.00';
            $line = bcmul((string) $unit, (string) $item->getQuantity(), 2);
            $total = bcadd($total, $line, 2);
        }

        $cart->setTotalPrice($total);
        $cart->setUpdatedAt(new DateTimeImmutable());

        return $total;
    }

    /**
     * Clear all items from the cart
     */
    public function clearCart(Cart $cart): void
    {
        // Use repository method to delete all cart items via DQL
        $this->cartRepository->clearAllItems($cart);

        // Clear the collection in the owning Cart entity and update totals
        $cart->getItems()->clear();
        $cart->setUpdatedAt(new DateTimeImmutable());
        $cart->setTotalPrice('0.00');

        $this->entityManager->flush();
    }

    /**
     * Get or create a cart for the current authenticated user
     */
    public function getOrCreateCart(): Cart
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedException('You must be logged in to access the cart.');
        }

        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }

        return $cart;
    }
}

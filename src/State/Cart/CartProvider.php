<?php

namespace App\State\Cart;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Dto\Cart\CartOutputDto;
use App\Repository\CartRepository;
use ApiPlatform\Metadata\Operation;
use App\Dto\Cart\CartItemOutputDto;
use App\Dto\Product\ProductOutputDto;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CartProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CartRepository $cartRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CartOutputDto
    {
        $user = $this->security->getUser();
        if (!$user) {
            return null;
        }
        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            // Auto-create empty cart if it doesn't exist
            $cart = new Cart();
            $cart->setUser($user);
            $this->entityManager->persist($cart);
            $this->entityManager->flush();
        }
        return $this->transformToDto($cart);
    }

    public function transformToDto(Cart $cart): CartOutputDto
    {
        $items = array_map(
            fn(CartItem $item) => $this->transformCartItemToDto($item),
            $cart->getItems()->toArray()
        );

        $totalItems = array_reduce(
            $items,
            fn(int $sum, CartItemOutputDto $item) => $sum + $item->quantity,
            0
        );

        $subtotal = array_reduce(
            $items,
            fn(string $sum, CartItemOutputDto $item) => bcadd($sum, $item->totalPrice, 2),
            '0.00'
        );

        return new CartOutputDto(
            id: $cart->getId(),
            items: $items,
            totalItems: $totalItems,
            subtotal: $subtotal,
            createdAt: $cart->getCreatedAt(),
            updatedAt: $cart->getUpdatedAt(),
        );
    }

    private function transformCartItemToDto(CartItem $cartItem): CartItemOutputDto
    {
        $quantity = $cartItem->getQuantity();
        $unitPrice = $cartItem->getUnitPrice();
        $totalPrice = bcmul($unitPrice, (string)$quantity, 2);

        return new CartItemOutputDto(
            id: $cartItem->getId(),
            product: $this->transformProductToDto($cartItem->getProduct()),
            quantity: $quantity,
            unitPrice: $unitPrice,
            totalPrice: $totalPrice,
        );
    }

    private function transformProductToDto($product): ProductOutputDto
    {
        return new ProductOutputDto(
            id: $product->getId(),
            name: $product->getName(),
            price: $product->getPrice(),
            stock: $product->getStock(),
            image: $product->getImageUrl(),
        );
    }
}

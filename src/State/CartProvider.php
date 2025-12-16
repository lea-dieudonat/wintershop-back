<?php

namespace App\State;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Dto\CartOutputDto;
use App\Dto\ProductOutputDto;
use App\Dto\CartItemOutputDto;
use App\Repository\CartRepository;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;

class CartProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly CartRepository $cartRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CartOutputDto
    {
        $user = $this->security->getUser();
        if (!$user) {
            return null;
        }
        $cart = $this->cartRepository->findOneBy(['user' => $user]);
        if (!$cart) {
            return null;
        }
        return $this->transformToDto($cart);
    }

    public function transformToDto(Cart $cart): CartOutputDto
    {
        $dto = new CartOutputDto();
        $dto->id = $cart->getId();
        $dto->createdAt = $cart->getCreatedAt();
        $dto->updatedAt = $cart->getUpdatedAt();

        $dto->items = array_map(
            fn(CartItem $item) => $this->transformCartItemToDto($item),
            $cart->getItems()->toArray()
        );

        $dto->totalItems = array_reduce(
            $dto->items,
            fn(int $sum, CartItemOutputDto $item) => $sum + $item->quantity,
            0
        );

        $dto->subtotal = array_reduce(
            $dto->items,
            fn(string $sum, CartItemOutputDto $item) => bcadd($sum, $item->totalPrice, 2),
            '0.00'
        );

        return $dto;
    }

    private function transformCartItemToDto(CartItem $cartItem): CartItemOutputDto
    {
        $dto = new CartItemOutputDto();
        $dto->id = $cartItem->getId();
        $dto->quantity = $cartItem->getQuantity();
        $dto->unitPrice = $cartItem->getUnitPrice();
        $dto->totalPrice = bcmul(
            $dto->unitPrice,
            (string)$dto->quantity,
            2
        );
        $dto->product = $this->transformProductToDto($cartItem->getProduct());
        return $dto;
    }

    private function transformProductToDto($product): ProductOutputDto
    {
        $dto = new ProductOutputDto();
        $dto->id = $product->getId();
        $dto->name = $product->getName();
        $dto->price = $product->getPrice();
        $dto->image = $product->getImageUrl();
        $dto->stock = $product->getStock();
        return $dto;
    }
}

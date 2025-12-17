<?php

namespace App\State;

use App\Service\CartService;
use InvalidArgumentException;
use App\Dto\Cart\CartOutputDto;
use App\Dto\Cart\CartItemInputDto;
use ApiPlatform\Metadata\Operation;
use App\Repository\ProductRepository;
use App\Repository\CartItemRepository;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\Metadata\Exception\AccessDeniedException;

class CartItemProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CartProvider $cartProvider,
        private readonly Security $security,
        private readonly ProductRepository $productRepository,
        private readonly CartItemRepository $cartItemRepository,
    ) {}
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartOutputDto|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedException('User not authenticated.');
        }

        // POST
        if ($operation instanceof \ApiPlatform\Metadata\Post) {
            assert($data instanceof CartItemInputDto);

            $product = $this->productRepository->find($data->productId);
            if (!$product) {
                throw new InvalidArgumentException('Product not found.');
            }
            try {
                $cart = $this->cartService->getOrCreateCart();
                $this->cartService->addProduct($cart, $product, $data->quantity);
                return $this->cartProvider->transformToDto($cart);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        // PATCH
        if ($operation instanceof \ApiPlatform\Metadata\Patch) {
            assert($data instanceof CartItemInputDto);

            $cartItem = $this->cartItemRepository->find($uriVariables['id']);
            if (!$cartItem || $cartItem->getCart()->getUser() !== $user) {
                throw new AccessDeniedException('Cart item not found or access denied.');
            }
            try {
                $cart = $cartItem->getCart();
                $this->cartService->updateQuantity($cart, $cartItem, $data->quantity);

                return $this->cartProvider->transformToDto($cart);
            } catch (InvalidArgumentException $e) {
                throw new InvalidArgumentException($e->getMessage());
            }
        }

        // DELETE
        if ($operation instanceof \ApiPlatform\Metadata\Delete) {
            $cartItem = $this->cartItemRepository->find($uriVariables['id']);
            if (!$cartItem || $cartItem->getCart()->getUser() !== $user) {
                throw new AccessDeniedException('Cart item not found or access denied.');
            }

            $cart = $cartItem->getCart();
            $this->cartService->removeProduct($cart, $cartItem);

            return $this->cartProvider->transformToDto($cart);
        }

        return null;
    }
}

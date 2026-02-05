<?php

namespace App\State\Cart;

use App\Service\CartService;
use App\Dto\Cart\CartOutputDto;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\Metadata\Exception\AccessDeniedException;

class CartProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CartProvider $cartProvider,
        private readonly Security $security,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CartOutputDto|null
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedException('User not authenticated.');
        }

        // DELETE /cart - Clear all items from the cart
        if ($operation instanceof \ApiPlatform\Metadata\Delete) {
            $cart = $this->cartService->getOrCreateCart();
            $this->cartService->clearCart($cart);
            return $this->cartProvider->transformToDto($cart);
        }

        return null;
    }
}

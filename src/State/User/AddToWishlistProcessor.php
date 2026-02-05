<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Processor pour ajouter un produit à la wishlist
 */
class AddToWishlistProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProductRepository $productRepository,
        private readonly EntityManagerInterface $entityManager
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('User must be authenticated');
        }

        $productId = $uriVariables['productId'] ?? null;

        if (!$productId) {
            throw new NotFoundHttpException('Product ID is required');
        }

        $product = $this->productRepository->find($productId);

        if (!$product) {
            throw new NotFoundHttpException('Product not found');
        }

        $user->addToWishlist($product);
        $this->entityManager->flush();

        return $user->getWishlist()->toArray();
    }
}

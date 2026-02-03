<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\Security\ChangePasswordInputDto;
use App\Entity\User;
use App\Mapper\UserMapper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ChangePasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof ChangePasswordInputDto) {
            throw new \InvalidArgumentException('Expected ChangePasswordInputDto');
        }

        // Récupérer l'utilisateur depuis la base de données
        $userId = $uriVariables['id'] ?? null;
        if (!$userId) {
            throw new \InvalidArgumentException('User ID is required');
        }

        $user = $this->entityManager->getRepository(User::class)->find($userId);
        if (!$user) {
            throw new \RuntimeException('User not found');
        }

        // Vérifier que l'ancien mot de passe est correct
        if (!$this->passwordHasher->isPasswordValid($user, $data->currentPassword)) {
            throw new BadRequestException('Current password is incorrect');
        }

        // Vérifier que le nouveau mot de passe est différent de l'ancien
        if ($this->passwordHasher->isPasswordValid($user, $data->newPassword)) {
            throw new BadRequestException('New password must be different from current password');
        }

        // Hasher et définir le nouveau mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($user, $data->newPassword);
        $user->setPassword($hashedPassword);

        $this->entityManager->flush();

        // Retourner le DTO de sortie
        return UserMapper::toOutputDto($user);
    }
}

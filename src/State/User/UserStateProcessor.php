<?php

namespace App\State\User;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Dto\User\UserInputDto;
use App\Entity\User;
use App\Mapper\UserMapper;
use Doctrine\ORM\EntityManagerInterface;

class UserStateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        if (!$data instanceof UserInputDto) {
            throw new \InvalidArgumentException('Expected UserInputDto');
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

        // Mettre à jour les propriétés
        $user->setFirstName($data->firstName);
        $user->setLastName($data->lastName);

        $this->entityManager->flush();

        // Retourner le DTO de sortie
        return UserMapper::toOutputDto($user);
    }
}

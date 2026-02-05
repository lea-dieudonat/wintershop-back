<?php

namespace App\Dto\User;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\Dto\Security\ChangePasswordInputDto;
use App\State\User\UserStateProvider;
use App\State\User\UserStateProcessor;
use App\State\User\ChangePasswordProcessor;
use Symfony\Component\Serializer\Attribute\Groups;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/users/{id}',
            security: "is_granted('ROLE_USER') or object.id == user.getId()",
            securityMessage: 'You do not have access to this resource.'
        ),
        new Patch(
            uriTemplate: '/users/{id}',
            security: "is_granted('ROLE_USER') or object.id == user.getId()",
            securityMessage: 'You do not have access to this resource.',
            input: UserInputDto::class
        ),
        new Post(
            uriTemplate: '/users/{id}/change-password',
            security: "is_granted('ROLE_USER') or object.id == user.getId()",
            securityMessage: 'You do not have access to this resource.',
            input: ChangePasswordInputDto::class,
            processor: ChangePasswordProcessor::class
        )
    ],
    normalizationContext: ['groups' => ['user:read']],
    provider: UserStateProvider::class,
    processor: UserStateProcessor::class,
)]
readonly class UserOutputDto
{
    public function __construct(
        #[Groups(['user:read'])]
        public int $id,

        #[Groups(['user:read'])]
        public string $email,

        #[Groups(['user:read'])]
        public string $firstName,

        #[Groups(['user:read'])]
        public string $lastName,

        #[Groups(['user:read'])]
        public \DateTimeImmutable $createdAt,
    ) {}
}

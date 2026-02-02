<?php

namespace App\Dto\User;

use Symfony\Component\Serializer\Attribute\Groups;

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

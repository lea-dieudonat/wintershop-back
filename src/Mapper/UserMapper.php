<?php

namespace App\Mapper;

use App\Dto\User\UserOutputDto;
use App\Entity\User;

class UserMapper
{
    public static function toOutputDto(User $user): UserOutputDto
    {
        return new UserOutputDto(
            id: $user->getId(),
            email: $user->getEmail(),
            firstName: $user->getFirstName(),
            lastName: $user->getLastName(),
            createdAt: $user->getCreatedAt(),
        );
    }
}

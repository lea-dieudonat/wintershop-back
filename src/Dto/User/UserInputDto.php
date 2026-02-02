<?php

namespace App\Dto\User;

use Symfony\Component\Validator\Constraints as Assert;

readonly class UserInputDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'user.firstName.notBlank')]
        #[Assert\Length(
            min: 2,
            max: 50,
            minMessage: 'user.firstName.minLength',
            maxMessage: 'user.firstName.maxLength'
        )]
        public string $firstName,

        #[Assert\NotBlank(message: 'user.lastName.notBlank')]
        #[Assert\Length(
            min: 2,
            max: 50,
            minMessage: 'user.lastName.minLength',
            maxMessage: 'user.lastName.maxLength'
        )]
        public string $lastName,
    ) {}
}

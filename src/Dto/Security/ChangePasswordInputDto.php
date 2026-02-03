<?php

namespace App\Dto\Security;

use Symfony\Component\Validator\Constraints as Assert;

readonly class ChangePasswordInputDto
{
    public function __construct(
        #[Assert\NotBlank(message: 'password.currentRequired')]
        public string $currentPassword,

        #[Assert\NotBlank(message: 'password.newRequired')]
        #[Assert\Length(
            min: 8,
            minMessage: 'password.minLength'
        )]
        #[Assert\Regex(
            pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
            message: 'password.complexity'
        )]
        public string $newPassword,

        #[Assert\NotBlank(message: 'password.confirmRequired')]
        #[Assert\EqualTo(
            propertyPath: 'newPassword',
            message: 'password.notMatch'
        )]
        public string $confirmPassword,
    ) {}
}

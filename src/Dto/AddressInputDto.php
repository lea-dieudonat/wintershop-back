<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class AddressInputDto
{
    #[Assert\NotBlank(message: 'First name should not be blank.')]
    #[Assert\Length(max: 255)]
    public ?string $firstName = null;

    #[Assert\NotBlank(message: 'Last name should not be blank.')]
    #[Assert\Length(max: 255)]
    public ?string $lastName = null;
    #[Assert\NotBlank(message: 'Address should not be blank.')]
    #[Assert\Length(max: 255)]
    public ?string $street = null;

    #[Assert\NotBlank(message: 'Postal code should not be blank.')]
    #[Assert\Length(max: 10)]
    public ?string $postalCode = null;

    #[Assert\NotBlank(message: 'City should not be blank.')]
    #[Assert\Length(max: 255)]
    public ?string $city = null;

    #[Assert\NotBlank(message: 'Country should not be blank.')]
    #[Assert\Length(max: 255)]
    public ?string $country = null;

    #[Assert\Length(max: 500)]
    public ?string $additionalInfo = null;

    #[Assert\Length(max: 20)]
    public ?string $phoneNumber = null;

    public bool $isDefault = false;
}

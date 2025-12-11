<?php

namespace App\Dto;

class AddressOutputDto
{
    public int $id;
    public string $firstName;
    public string $lastName;
    public string $street;
    public string $postalCode;
    public string $city;
    public string $country;
    public ?string $additionalInfo = null;
    public ?string $phoneNumber = null;
    public bool $isDefault;
    public string $fullAddress;
    public \DateTimeInterface $createdAt;
    public ?\DateTimeInterface $updatedAt = null;
}

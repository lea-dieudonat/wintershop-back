<?php

namespace App\Dto\Address;

use DateTimeImmutable;

readonly class AddressOutputDto
{
    public function __construct(
        public int $id,
        public string $firstName,
        public string $lastName,
        public string $street,
        public string $postalCode,
        public string $city,
        public string $country,
        public bool $isDefault,
        public string $fullAddress,
        public DateTimeImmutable $createdAt,
        public ?string $additionalInfo = null,
        public ?string $phoneNumber = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public static function fromEntity($address): self
    {
        return new self(
            id: $address->getId(),
            firstName: $address->getFirstName(),
            lastName: $address->getLastName(),
            street: $address->getStreet(),
            postalCode: $address->getPostalCode(),
            city: $address->getCity(),
            country: $address->getCountry(),
            isDefault: $address->isDefault(),
            fullAddress: $address->getFullAddress(),
            createdAt: $address->getCreatedAt(),
            additionalInfo: $address->getAdditionalInfo(),
            phoneNumber: $address->getPhoneNumber(),
            updatedAt: $address->getUpdatedAt(),
        );
    }
}

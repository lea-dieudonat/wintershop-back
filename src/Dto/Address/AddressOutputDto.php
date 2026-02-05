<?php

namespace App\Dto\Address;

use DateTimeImmutable;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;
use App\State\Address\AddressProvider;
use App\State\Address\AddressProcessor;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Patch(),
        new Delete(
            security: "is_granted('ROLE_USER')"
        ),
    ],
    input: AddressInputDto::class,
    provider: AddressProvider::class,
    processor: AddressProcessor::class,
    security: "is_granted('ROLE_USER')",
)]
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

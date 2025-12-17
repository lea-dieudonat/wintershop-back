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
}

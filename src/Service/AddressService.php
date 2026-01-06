<?php

namespace App\Service;

use App\Dto\Address\AddressOutputDto;
use App\Entity\Address;

class AddressService
{
    public function toDto(Address $address): AddressOutputDto
    {
        return new AddressOutputDto(
            id: $address->getId(),
            firstName: $address->getFirstName(),
            lastName: $address->getLastName(),
            street: $address->getStreet(),
            postalCode: $address->getPostalCode(),
            city: $address->getCity(),
            country: $address->getCountry(),
            isDefault: $address->isDefault() ?? false,
            fullAddress: implode(
                ', ',
                array_filter([
                    $address->getStreet(),
                    $address->getAdditionalInfo(),
                    trim(sprintf('%s %s', $address->getPostalCode(), $address->getCity())),
                    $address->getCountry(),
                    $address->getPhoneNumber(),
                ])
            ),
            createdAt: $address->getCreatedAt(),
            additionalInfo: $address->getAdditionalInfo(),
            phoneNumber: $address->getPhoneNumber(),
            updatedAt: $address->getUpdatedAt(),
        );
    }
}

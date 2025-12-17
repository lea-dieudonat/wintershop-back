<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\State\ProviderInterface;
use App\Repository\AddressRepository;
use App\Entity\Address;
use App\Dto\Address\AddressOutputDto;

class AddressProvider implements ProviderInterface
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
        private Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();

        if (!$user) {
            return null;
        }

        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            $addresses = $this->addressRepository->findBy(['user' => $user]);

            return array_map(
                fn(Address $address) => $this->transformToDto($address),
                $addresses
            );
        }

        if (isset($uriVariables['id'])) {
            $address = $this->addressRepository->find($uriVariables['id']);

            if (!$address || $address->getUser() !== $user) {
                return null;
            }

            return $this->transformToDto($address);
        }

        return null;
    }

    public function transformToDto(Address $address): AddressOutputDto
    {
        $fullAddress = implode(
            ', ',
            array_filter([
                $address->getStreet(),
                $address->getAdditionalInfo(),
                trim(sprintf('%s %s', $address->getPostalCode(), $address->getCity())),
                $address->getCountry(),
                $address->getPhoneNumber(),
            ])
        );

        return new AddressOutputDto(
            id: $address->getId(),
            firstName: $address->getFirstName(),
            lastName: $address->getLastName(),
            street: $address->getStreet(),
            postalCode: $address->getPostalCode(),
            city: $address->getCity(),
            country: $address->getCountry(),
            additionalInfo: $address->getAdditionalInfo(),
            phoneNumber: $address->getPhoneNumber(),
            isDefault: $address->isDefault() ?? false,
            fullAddress: $fullAddress,
            createdAt: $address->getCreatedAt(),
            updatedAt: $address->getUpdatedAt(),
        );
    }
}

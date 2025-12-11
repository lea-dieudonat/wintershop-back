<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\State\ProviderInterface;
use App\Repository\AddressRepository;
use App\Entity\Address;
use App\Dto\AddressOutputDto;

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
        $dto = new AddressOutputDto();
        $dto->id = $address->getId();
        $dto->firstName = $address->getFirstName();
        $dto->lastName = $address->getLastName();
        $dto->street = $address->getStreet();
        $dto->postalCode = $address->getPostalCode();
        $dto->city = $address->getCity();
        $dto->country = $address->getCountry();
        $dto->additionalInfo = $address->getAdditionalInfo();
        $dto->phoneNumber = $address->getPhoneNumber();
        $dto->isDefault = $address->isDefault();
        $dto->createdAt = $address->getCreatedAt();
        $dto->updatedAt = $address->getUpdatedAt();

        $dto->fullAddress = sprintf(
            "%s, %s %s, %s",
            $address->getPostalCode(),
            $address->getCity(),
            $address->getCountry(),
            $address->getPhoneNumber() ?? ''
        );

        return $dto;
    }
}

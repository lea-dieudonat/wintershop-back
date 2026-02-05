<?php

namespace App\State\Address;

use App\Entity\User;
use App\Entity\Address;
use App\State\Address\AddressProvider;
use ApiPlatform\Metadata\Operation;
use App\Dto\Address\AddressInputDto;
use App\Dto\Address\AddressOutputDto;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AddressProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly AddressRepository $addressRepository,
        private readonly EntityManagerInterface $entityManager,
        private Security $security,
        private AddressProvider $provider,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?AddressOutputDto
    {
        $user = $this->security->getUser();
        if (!$user) {
            throw new AccessDeniedHttpException('User not authenticated.');
        }

        // DELETE
        if ($operation instanceof \ApiPlatform\Metadata\Delete) {
            if (!$data instanceof Address) {
                throw new \InvalidArgumentException('Expected Address entity for delete operation');
            }

            if ($data->getUser() !== $user) {
                throw new AccessDeniedHttpException('You do not have permission to delete this address.');
            }

            $this->entityManager->remove($data);
            $this->entityManager->flush();
            return null;
        }

        assert($data instanceof AddressInputDto);

        // POST
        if ($operation instanceof \ApiPlatform\Metadata\Post) {
            $address = new Address();
            $address->setUser($user);
            $this->mapDtoToEntity($data, $address);
            if ($data->isDefault) {
                $this->unsetOtherDefaultAddresses($user);
            }
            $this->entityManager->persist($address);
            $this->entityManager->flush();
            return $this->provider->transformToDto($address);
        }

        // PUT/PATCH
        if ($operation instanceof \ApiPlatform\Metadata\Put || $operation instanceof \ApiPlatform\Metadata\Patch) {
            $address = $this->addressRepository->find($uriVariables['id']);
            if (!$address || $address->getUser() !== $user) {
                throw new AccessDeniedHttpException('You do not have permission to modify this address.');
            }
            $this->mapDtoToEntity($data, $address);
            if ($data->isDefault) {
                $this->unsetOtherDefaultAddresses($user, $address->getId());
            }
            $this->entityManager->flush();
            return $this->provider->transformToDto($address);
        }
        return null;
    }

    private function mapDtoToEntity(AddressInputDto $dto, Address $address): void
    {
        $address->setFirstName($dto->firstName);
        $address->setLastName($dto->lastName);
        $address->setStreet($dto->street);
        $address->setPostalCode($dto->postalCode);
        $address->setCity($dto->city);
        $address->setCountry($dto->country);
        $address->setAdditionalInfo($dto->additionalInfo);
        $address->setPhoneNumber($dto->phoneNumber);
        $address->setIsDefault($dto->isDefault);
    }

    private function unsetOtherDefaultAddresses(User $user, ?Address $exceptAddress = null): void
    {
        $defaultAddresses = $this->addressRepository->findBy(['user' => $user, 'isDefault' => true]);

        foreach ($defaultAddresses as $address) {
            if ($exceptAddress === null || $address->getId() !== $exceptAddress->getId()) {
                $address->setIsDefault(false);
            }
        }
    }
}

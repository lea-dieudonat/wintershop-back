<?php

namespace App\Tests\Factory;

use App\Entity\Address;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Address>
 */
final class AddressFactory extends PersistentProxyObjectFactory
{
    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#factories-as-services
     *
     * @todo inject services if required
     */
    public function __construct() {}

    #[\Override]
    public static function class(): string
    {
        return Address::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     *
     * @todo add your default values here
     */
    #[\Override]
    protected function defaults(): array|callable
    {
        return [
            'city' => self::faker()->city(),
            'country' => 'France',
            'createdAt' => \DateTimeImmutable::createFromMutable(self::faker()->dateTime()),
            'firstName' => self::faker()->firstName(),
            'isDefault' => false,
            'lastName' => self::faker()->lastName(),
            'phoneNumber' => self::faker()->phoneNumber(),
            'postalCode' => self::faker()->postcode(),
            'street' => self::faker()->streetAddress(),
            'user' => UserFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    #[\Override]
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (Address $address): void {
                // If an associated User exists, prefer copying the user's name
                $user = $address->getUser();
                if ($user) {
                    if (empty($address->getFirstName())) {
                        $address->setFirstName($user->getFirstName());
                    }
                    if (empty($address->getLastName())) {
                        $address->setLastName($user->getLastName());
                    }
                }
            });
    }
}

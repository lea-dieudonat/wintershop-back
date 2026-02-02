<?php

namespace App\State;

use ApiPlatform\Doctrine\Orm\State\ItemProvider;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Mapper\UserMapper;

class UserStateProvider implements ProviderInterface
{
    public function __construct(
        private readonly ItemProvider $itemProvider,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->itemProvider->provide($operation, $uriVariables, $context);

        if (!$user) {
            return null;
        }

        return UserMapper::toOutputDto($user);
    }
}

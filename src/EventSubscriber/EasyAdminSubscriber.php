<?php
namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;

class EasyAdminSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ){
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => ['hashPassword'],
            BeforeEntityUpdatedEvent::class => ['hashPassword'],
        ];
    }

    public function hashPassword(BeforeEntityPersistedEvent|BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();

        if (! $entity instanceof User) {
            return;
        }

        $plainPassword = $entity->getPassword();

        if (empty($plainPassword)) {
            return;
        }
        
        // If the password is already hashed, do nothing
        if (strlen($plainPassword) === 60 && preg_match('/^\$2[ayb]\$.{56}$/', $plainPassword)) {
            return;
        }

        $hashedPassword = $this->passwordHasher->hashPassword(
            $entity,
            $plainPassword
        );

        $entity->setPassword($hashedPassword);
    }
}
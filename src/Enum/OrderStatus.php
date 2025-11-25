<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public function getTranslationKey(): string
    {
        return 'order.status.' . $this->value;
    }

    public function getBadgeColor(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::PAID => 'info',
            self::SHIPPED => 'primary',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
        };
    }

        /**
     * Vérifie si le statut peut être annulé.
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::PENDING, self::PAID]);
    }

    /**
     * Retourne les transitions possibles depuis ce statut.
     * 
     * @return array<OrderStatus>
     */
    public function getAllowedTransitions(): array
    {
        return match($this) {
            self::PENDING => [self::PAID, self::CANCELLED],
            self::PAID => [self::SHIPPED, self::CANCELLED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [],
            self::CANCELLED => [],
        };
    }

    /**
     * Vérifie si on peut transitionner vers un autre statut.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedTransitions());
    }
}
<?php

namespace App\Enum;

enum OrderStatus: string
{
    case PENDING = 'pending'; // Créé mais non payé
    case PAID = 'paid'; // Payé mais non expédié
    case PROCESSING = 'processing'; // En cours de traitement
    case SHIPPED = 'shipped'; // Expédié
    case DELIVERED = 'delivered'; // Livré
    case CANCELLED = 'cancelled'; // Annulé (avant paiement)
    case REFUND_REQUESTED = 'refund_requested'; // Demande de remboursement
    case REFUNDED = 'refunded'; // Remboursé

    public function getTranslationKey(): string
    {
        return 'order.status.' . $this->value;
    }

    public function getBadgeColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::PAID => 'info',
            self::PROCESSING => 'primary',
            self::SHIPPED => 'primary',
            self::DELIVERED => 'success',
            self::CANCELLED => 'danger',
            self::REFUND_REQUESTED => 'warning',
            self::REFUNDED => 'secondary',
        };
    }

    /**
     * Vérifie si le statut peut être annulé.
     */
    public function isCancellable(): bool
    {
        return $this === self::PENDING;
    }

    /**
     * Vérifie si on peut demander un remboursement.
     */
    public function canRequestRefund(): bool
    {
        // On peut demander un remboursement si la commande est payée, en cours de traitement ou expédiée
        return in_array(
            $this,
            [
                self::PAID,
                self::PROCESSING,
                self::SHIPPED,
                self::DELIVERED,
            ]
        );
    }

    /**
     * Retourne les transitions possibles depuis ce statut.
     * 
     * @return array<OrderStatus>
     */
    public function getAllowedTransitions(): array
    {
        return match ($this) {
            self::PENDING => [self::PAID, self::CANCELLED],
            self::PAID => [self::PROCESSING],
            self::PROCESSING => [self::SHIPPED, self::REFUND_REQUESTED],
            self::SHIPPED => [self::DELIVERED, self::REFUND_REQUESTED],
            self::DELIVERED => [self::REFUND_REQUESTED],
            self::REFUND_REQUESTED => [self::REFUNDED, self::DELIVERED],
            self::CANCELLED => [],
            self::REFUNDED => [],
        };
    }

    /**
     * Vérifie si on peut transitionner vers un autre statut.
     */
    public function canTransitionTo(OrderStatus $newStatus): bool
    {
        return in_array($newStatus, $this->getAllowedTransitions());
    }

    /**
     * Retourne un label lisible pour l'affichage.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::PAID => 'Paid',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::CANCELLED => 'Cancelled',
            self::REFUND_REQUESTED => 'Refund Requested',
            self::REFUNDED => 'Refunded',
        };
    }
}

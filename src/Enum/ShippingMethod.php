<?php

namespace App\Enum;

enum ShippingMethod: string
{
    case STANDARD = 'standard';
    case EXPRESS = 'express';
    case RELAY_POINT = 'relay_point';

    public function getLabel(): string
    {
        return match ($this) {
            self::STANDARD => 'Standard Shipping',
            self::EXPRESS => 'Express Shipping',
            self::RELAY_POINT => 'Relay Point Pickup',
        };
    }

    public function getCost(): float
    {
        return match ($this) {
            self::STANDARD => '2.99',
            self::EXPRESS => '4.99',
            self::RELAY_POINT => '0.00',
        };
    }

    public function getDeliveryTime(): string
    {
        return match ($this) {
            self::STANDARD => '3-5 business days',
            self::EXPRESS => '1-2 business days',
            self::RELAY_POINT => '5-7 business days',
        };
    }

    /**
     * Get actual cost (relay point is always free).
     */
    public function getActualCost(string $orderAmount): string
    {
        return $this->getCost();
    }
}

<?php

namespace App\Exception;

class OrderNotRefundableException extends \Exception
{
    public static function notDelivered(): self
    {
        return new self('orders.refund.notDelivered');
    }

    public static function alreadyRequested(): self
    {
        return new self('orders.refund.alreadyRequested');
    }

    public static function deadlineExpired(): self
    {
        return new self('orders.refund.deadlineExpired');
    }
}

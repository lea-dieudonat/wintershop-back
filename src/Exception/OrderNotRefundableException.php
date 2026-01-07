<?php

namespace App\Exception;

class OrderNotRefundableException extends \Exception
{
    public static function notDelivered(): self
    {
        return new self('Only delivered orders can be refunded');
    }

    public static function alreadyRequested(): self
    {
        return new self('A refund request already exists for this order');
    }

    public static function deadlineExpired(): self
    {
        return new self('The 14-day refund period has expired');
    }
}

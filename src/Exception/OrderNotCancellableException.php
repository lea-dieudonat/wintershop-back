<?php

namespace App\Exception;

class OrderNotCancellableException extends \Exception
{
    public static function invalidStatus(): self
    {
        return new self('orders.cancel.invalidStatus');
    }

    public static function deadlineExpired(): self
    {
        return new self('orders.cancel.deadlineExpired');
    }
}

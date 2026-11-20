<?php

namespace App\Services\Payments;

use RuntimeException;

class PaymentException extends RuntimeException
{
    public function __construct(string $message, public readonly string $field = 'code')
    {
        parent::__construct($message);
    }
}

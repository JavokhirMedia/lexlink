<?php

namespace App\Services\Payments;

use InvalidArgumentException;

/** Resolves a provider by code. Replace the Mock entries with real merchant integrations later. */
class PaymentProviders
{
    public const CODES = ['click', 'payme'];

    public function for(string $code): PaymentProvider
    {
        if (! in_array($code, self::CODES, true)) {
            throw new InvalidArgumentException("Unknown payment provider [{$code}].");
        }

        return new MockPaymentProvider($code);
    }
}

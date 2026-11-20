<?php

namespace App\Services\Payments;

use App\Models\User;

/**
 * Payment gateway contract shaped after Click / Payme merchant flows:
 *   prepare  -> create a payment for an amount + card, gateway sends an SMS (OTP)
 *   complete -> confirm with the OTP, gateway debits the card
 * Crediting the wallet is done by the caller (BillingService::topup) using the returned external id.
 *
 * Swapping to real Click/Payme = a new class implementing this interface + registering it in PaymentProviders.
 */
interface PaymentProvider
{
    /** Provider code: click|payme */
    public function code(): string;

    /** Human-readable name shown on the checkout sheet. */
    public function name(): string;

    /**
     * @param  array{card_number:string, expiry:string}  $card
     * @return array{payment_id:string, amount:int, card_masked:string, phone_masked:string, expires_in:int}
     *
     * @throws PaymentException
     */
    public function prepare(User $user, int $amount, array $card): array;

    /**
     * @return array{payment_id:string, external_id:string, amount:int, status:string}
     *
     * @throws PaymentException
     */
    public function complete(User $user, string $paymentId, string $code): array;
}

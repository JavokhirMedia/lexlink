<?php

namespace App\Services\Payments;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Demo gateway — no real money moves. Any card that passes the Luhn-free 16-digit check works,
 * the SMS code is any 6 digits (UI hint: 666666). Pending payments live in the cache for 10 minutes.
 */
class MockPaymentProvider implements PaymentProvider
{
    public const TTL = 600;

    public function __construct(private string $code) {}

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return match ($this->code) {
            'click' => 'Click',
            'payme' => 'Payme',
            default => ucfirst($this->code),
        };
    }

    public function prepare(User $user, int $amount, array $card): array
    {
        $digits = preg_replace('/\D/', '', $card['card_number']);

        if (strlen($digits) !== 16) {
            throw new PaymentException("Karta raqami 16 ta raqamdan iborat bo'lishi kerak.", 'card_number');
        }

        if (! preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $card['expiry'], $m)) {
            throw new PaymentException("Amal qilish muddati OO/YY ko'rinishida bo'lishi kerak.", 'expiry');
        }

        $paymentId = $this->code.'_'.Str::lower(Str::random(20));

        Cache::put($this->key($paymentId), [
            'user_id' => $user->id,
            'amount' => $amount,
            'card_masked' => substr($digits, 0, 4).' **** **** '.substr($digits, -4),
            'status' => 'pending',
        ], self::TTL);

        return [
            'payment_id' => $paymentId,
            'amount' => $amount,
            'card_masked' => substr($digits, 0, 4).' **** **** '.substr($digits, -4),
            'phone_masked' => '+998 ** *** ** '.substr(preg_replace('/\D/', '', (string) $user->phone) ?: '0000', -2),
            'expires_in' => self::TTL,
        ];
    }

    public function complete(User $user, string $paymentId, string $code): array
    {
        $payment = Cache::get($this->key($paymentId));

        if (! $payment || $payment['user_id'] !== $user->id) {
            throw new PaymentException("To'lov topilmadi yoki muddati tugadi. Qaytadan urinib ko'ring.", 'payment_id');
        }

        if (! preg_match('/^\d{6}$/', $code)) {
            throw new PaymentException("SMS kod 6 ta raqamdan iborat bo'lishi kerak.");
        }

        if ($payment['status'] === 'completed') {
            return ['payment_id' => $paymentId, 'external_id' => $paymentId, 'amount' => $payment['amount'], 'status' => 'completed'];
        }

        Cache::put($this->key($paymentId), [...$payment, 'status' => 'completed'], self::TTL);

        return ['payment_id' => $paymentId, 'external_id' => $paymentId, 'amount' => $payment['amount'], 'status' => 'completed'];
    }

    private function key(string $paymentId): string
    {
        return 'mockpay:'.$paymentId;
    }
}

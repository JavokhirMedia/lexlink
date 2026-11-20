<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown by BillingService when a paid action cannot be covered:
 *  - reason "plan":    the current plan does not allow extra (paid) requests (Start plan)
 *  - reason "balance": the wallet balance is lower than the price
 *
 * Rendered as a validation-style error under the `billing` key (or 402 JSON).
 */
class InsufficientBalance extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $reason = 'balance',
        public readonly int $required = 0,
        public readonly int $balance = 0,
    ) {
        parent::__construct($message);
    }

    public static function planDoesNotAllow(): self
    {
        return new self(
            "Joriy tarifingiz so'rov yuborishga ruxsat bermaydi. Advokat tarifiga o'ting.",
            'plan',
        );
    }

    public static function forAmount(int $required, int $balance): self
    {
        $fmt = fn (int $v) => number_format($v, 0, ',', ' ');

        return new self(
            "Balans yetarli emas: {$fmt($required)} so'm kerak, balansda {$fmt($balance)} so'm. Balansni to'ldiring.",
            'balance',
            $required,
            $balance,
        );
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $this->getMessage(),
                'reason' => $this->reason,
                'required' => $this->required,
                'balance' => $this->balance,
            ], 402);
        }

        return back()->withErrors(['billing' => $this->getMessage()]);
    }
}

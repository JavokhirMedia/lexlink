<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentProvider;
use App\Services\Payments\PaymentProviders;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Provider-shaped endpoints (prepare -> complete) used by the demo checkout sheet.
 * With a real merchant integration the same two steps map onto the gateway's API / callbacks.
 */
class MockPaymentController extends Controller
{
    public function __construct(private PaymentProviders $providers, private BillingService $billing) {}

    public function prepare(Request $request, string $provider): JsonResponse
    {
        $gateway = $this->resolve($provider);

        $data = $request->validate([
            'amount' => ['required', 'integer', 'min:'.BillingController::TOPUP_MIN, 'max:'.BillingController::TOPUP_MAX],
            'card_number' => ['required', 'string', 'max:32'],
            'expiry' => ['required', 'string', 'max:5'],
        ]);

        try {
            $payment = $gateway->prepare($request->user(), (int) $data['amount'], [
                'card_number' => $data['card_number'],
                'expiry' => $data['expiry'],
            ]);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        return response()->json($payment);
    }

    public function complete(Request $request, string $provider): JsonResponse
    {
        $gateway = $this->resolve($provider);

        $data = $request->validate([
            'payment_id' => ['required', 'string', 'max:64'],
            'code' => ['required', 'string'],
        ]);

        try {
            $result = $gateway->complete($request->user(), $data['payment_id'], $data['code']);
        } catch (PaymentException $e) {
            throw ValidationException::withMessages([$e->field => $e->getMessage()]);
        }

        $tx = $this->billing->topup($request->user(), $result['amount'], $gateway->code(), $result['external_id']);

        $amount = number_format($result['amount'], 0, ',', ' ');
        Inertia::flash('toast', ['type' => 'success', 'message' => "Balans {$amount} so'mga to'ldirildi."]);

        return response()->json([
            'status' => $result['status'],
            'transaction_id' => $tx->id,
            'amount' => (int) $tx->amount,
            'balance' => (int) $tx->balance_after,
            'redirect' => route('billing.index'),
        ]);
    }

    private function resolve(string $provider): PaymentProvider
    {
        abort_unless(in_array($provider, PaymentProviders::CODES, true), 404);

        return $this->providers->for($provider);
    }
}

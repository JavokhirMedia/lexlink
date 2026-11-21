<?php

namespace App\Http\Controllers\MockGov;

use App\Http\Controllers\Controller;
use App\Models\Mock\MockAdvocateLicense;
use App\Models\Mock\MockAdvocateOrder;
use App\Support\Pinfl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * "Advokatlar palatasi — elektron orderlar reyestri" (fictional).
 * Advokatura to'g'risidagi Qonun 6- va 7¹-moddalari: order advokatning ishonch bildiruvchini vakillik qilish vakolatini tasdiqlaydi.
 */
class AdvocateOrderController extends Controller
{
    use Formats;

    public function show(string $number): JsonResponse
    {
        $row = MockAdvocateOrder::where('order_number', strtoupper(trim($number)))->first();

        if (! $row) {
            return $this->notFound('Elektron order reyestrda topilmadi.');
        }

        return response()->json(self::payload($row) + ['checked_at' => $this->ts()]);
    }

    /** Issue a new electronic order for an active licence (demo of e-order issuance). */
    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'license_number' => ['required', 'string', 'max:32'],
            'client_pinfl' => ['required', 'string', 'regex:/^\d{14}$/'],
            'client_name' => ['required', 'string', 'max:255'],
            'matter' => ['required', 'string', 'max:255'],
        ]);
        if ($v->fails()) {
            return response()->json(['error' => 'validation_error', 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }
        $data = $v->validated();

        $license = MockAdvocateLicense::where('license_number', strtoupper(trim($data['license_number'])))->first();
        if (! $license) {
            return $this->notFound('Advokatlik litsenziyasi reestrda topilmadi.');
        }
        if ($license->status !== 'active') {
            return response()->json([
                'error' => 'license_inactive',
                'message' => "Litsenziya {$license->license_number} faol emas — order berib bo'lmaydi.",
            ], 422);
        }

        $row = DB::transaction(function () use ($data, $license) {
            $year = now()->setTimezone('Asia/Tashkent')->year;
            $last = MockAdvocateOrder::where('order_number', 'like', "ORD-{$year}-%")->lockForUpdate()->pluck('order_number')
                ->map(fn ($n) => (int) substr($n, -6))->max() ?? 0;

            return MockAdvocateOrder::create([
                'order_number' => sprintf('ORD-%d-%06d', $year, max($last, 900) + 1),
                'license_number' => $license->license_number,
                'advocate_name' => $license->full_name,
                'client_pinfl' => $data['client_pinfl'],
                'client_name' => trim($data['client_name']),
                'matter' => trim($data['matter']),
                'issued_at' => now()->setTimezone('Asia/Tashkent')->toDateString(),
                'status' => 'active',
            ]);
        });

        return response()->json(self::payload($row) + ['checked_at' => $this->ts()], 201);
    }

    public static function payload(MockAdvocateOrder $o): array
    {
        return [
            'order_number' => $o->order_number,
            'license_number' => $o->license_number,
            'advocate_name' => $o->advocate_name,
            'client' => ['pinfl' => $o->client_pinfl, 'full_name' => $o->client_name, 'pinfl_masked' => Pinfl::mask($o->client_pinfl)],
            'matter' => $o->matter,
            'issued_at' => $o->issued_at->toDateString(),
            'status' => $o->status,
            'registry' => 'Advokatlar palatasi — elektron orderlar reyestri (demo)',
        ];
    }
}

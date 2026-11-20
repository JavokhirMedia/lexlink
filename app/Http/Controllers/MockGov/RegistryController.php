<?php

namespace App\Http\Controllers\MockGov;

use App\Http\Controllers\Controller;
use App\Models\Mock\MockAdvocateLicense;
use App\Models\Mock\MockCourtCase;
use App\Models\Mock\MockCourtDecision;
use App\Support\Pinfl;
use Illuminate\Http\JsonResponse;

/** Advocate licence registry + court registries. */
class RegistryController extends Controller
{
    use Formats;

    public function advocate(string $license): JsonResponse
    {
        $row = MockAdvocateLicense::where('license_number', strtoupper(trim($license)))->first();

        if (! $row) {
            return $this->notFound('Advokatlik litsenziyasi reestrda topilmadi.');
        }

        return response()->json([
            'license_number' => $row->license_number,
            'full_name' => $row->full_name,
            'pinfl' => $row->pinfl,
            'status' => $row->status,
            'bureau_name' => $row->bureau_name,
            'issued_at' => $row->issued_at->toDateString(),
            'revoked_at' => $row->revoked_at?->toDateString(),
            'checked_at' => $this->ts(),
        ]);
    }

    public function decision(string $number): JsonResponse
    {
        $row = MockCourtDecision::where('number', trim($number))->first();

        if (! $row) {
            return $this->notFound('Sud qarori / ajrimi reestrda topilmadi.');
        }

        return response()->json(self::decisionPayload($row) + ['checked_at' => $this->ts()]);
    }

    public function courtCase(string $number): JsonResponse
    {
        $row = MockCourtCase::where('case_number', trim($number))->first();

        if (! $row) {
            return $this->notFound('Sud ishi topilmadi.');
        }

        return response()->json(self::casePayload($row));
    }

    public static function decisionPayload(MockCourtDecision $d): array
    {
        $today = now()->setTimezone('Asia/Tashkent')->toDateString();

        return [
            'number' => $d->number,
            'case_number' => $d->case_number,
            'court' => $d->court,
            'judge' => $d->judge,
            'decision_type' => $d->decision_type,
            'subject' => ['pinfl' => $d->subject_pinfl, 'full_name' => $d->subject_name],
            'allowed_types' => array_values($d->allowed_types ?? []),
            'requester_license' => $d->requester_license,
            'issued_at' => $d->issued_at->toDateString(),
            'expires_at' => $d->expires_at->toDateString(),
            'is_valid' => $d->expires_at->toDateString() >= $today,
            'summary' => $d->summary,
        ];
    }

    /** Court case card; party PINFLs are masked. */
    public static function casePayload(MockCourtCase $c): array
    {
        return [
            'case_number' => $c->case_number,
            'court' => $c->court,
            'judge' => $c->judge,
            'category' => $c->category,
            'status' => $c->status,
            'parties' => collect($c->parties ?? [])->map(fn (array $p) => [
                'role' => $p['role'] ?? null,
                'name' => $p['name'] ?? null,
                'pinfl' => isset($p['pinfl']) ? Pinfl::mask($p['pinfl']) : null,
            ])->all(),
            'hearings' => $c->hearings ?? [],
            'decisions' => $c->decisions ?? [],
        ];
    }
}

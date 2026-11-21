<?php

namespace App\Http\Controllers\Cases;

use App\Enums\AuditAction;
use App\Enums\LegalBasisType;
use App\Http\Controllers\Controller;
use App\Models\CaseClientLink;
use App\Models\LegalCase;
use App\Services\AuditLogger;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Support\Pinfl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Advocate creates a read-only access link for the client (ishonch bildiruvchi).
 * Valid only if the case has a verified advocate-order basis, or a registry order of this
 * advocate, whose client PINFL is known — the client later proves identity with OneID.
 */
class ClientLinkController extends Controller
{
    public function store(Request $request, LegalCase $legalCase, GovDataProvider $gov, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('addLegalBasis', $legalCase);

        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:32'],
        ], ['order_number.required' => 'Advokat orderi raqamini tanlang.']);

        $number = strtoupper(trim($data['order_number']));
        [$hash, $clientName] = $this->clientFromCase($legalCase, $number)
            ?? $this->clientFromRegistry($gov, $number, (string) $request->user()->advocateProfile?->license_number);

        $link = CaseClientLink::create([
            'case_id' => $legalCase->id,
            'order_number' => $number,
            'client_pinfl_hash' => $hash,
            'client_name' => $clientName,
            'token' => Str::random(48),
            'created_by' => $request->user()->id,
        ]);

        $audit->log(AuditAction::ClientLinkCreated, $link, [
            'order_number' => $number,
            'client_name' => $clientName,
        ], $legalCase->id);

        Inertia::flash('client_link_url', route('client.access', $link->token));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mijoz uchun kirish havolasi yaratildi.']);

        return back();
    }

    public function destroy(Request $request, LegalCase $legalCase, CaseClientLink $link, AuditLogger $audit): RedirectResponse
    {
        abort_unless((int) $link->case_id === (int) $legalCase->id, 404);
        Gate::authorize('addLegalBasis', $legalCase);

        if ($link->revoked_at === null) {
            $link->forceFill(['revoked_at' => now()])->save();
            $audit->log(AuditAction::ClientLinkRevoked, $link, ['order_number' => $link->order_number], $legalCase->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Mijoz havolasi bekor qilindi.']);

        return back();
    }

    /** @return array{0: string, 1: ?string}|null */
    private function clientFromCase(LegalCase $case, string $number): ?array
    {
        $basis = $case->legalBases()
            ->where('type', LegalBasisType::AdvocateOrder->value)
            ->where('number', $number)
            ->where('verification_status', 'verified')
            ->latest('id')->first();

        $hash = $basis?->verification_payload['client_pinfl_hash'] ?? null;

        return $hash ? [$hash, $basis->verification_payload['client_name'] ?? null] : null;
    }

    /** @return array{0: string, 1: ?string} */
    private function clientFromRegistry(GovDataProvider $gov, string $number, string $licence): array
    {
        try {
            $order = $gov->advocateOrder($number);
        } catch (GovApiException) {
            $order = null;
        }

        $ok = $order !== null
            && ($order['status'] ?? null) === 'active'
            && $licence !== ''
            && strtoupper((string) ($order['license_number'] ?? '')) === strtoupper($licence)
            && Pinfl::isValid((string) ($order['client']['pinfl'] ?? ''));

        if (! $ok) {
            throw ValidationException::withMessages([
                'order_number' => "Mijoz havolasi faqat sizning faol, tasdiqlangan advokat orderingiz asosida yaratiladi (mijoz JShShIR'i orderda ma'lum bo'lishi shart).",
            ]);
        }

        return [Pinfl::hash((string) $order['client']['pinfl']), $order['client']['full_name'] ?? null];
    }
}

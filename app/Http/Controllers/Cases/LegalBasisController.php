<?php

namespace App\Http\Controllers\Cases;

use App\Enums\AuditAction;
use App\Enums\LegalBasisType;
use App\Http\Controllers\Controller;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Services\AuditLogger;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Services\LegalBasisVerifier;
use App\Support\Pinfl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LegalBasisController extends Controller
{
    public function store(Request $request, LegalCase $legalCase, LegalBasisVerifier $verifier, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('addLegalBasis', $legalCase);

        $isOrder = $request->input('type') === LegalBasisType::AdvocateOrder->value;

        $data = $request->validate([
            'type' => ['required', Rule::enum(LegalBasisType::class)],
            'number' => ['required', 'string', 'max:64'],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'subject_pinfl' => [Rule::requiredIf($isOrder), 'nullable', 'string', function ($attr, $value, $fail) {
                if ($value !== null && ! Pinfl::isValid($value)) {
                    $fail("JShShIR 14 ta raqamdan iborat bo'lishi kerak.");
                }
            }],
            'subject_name' => ['nullable', 'string', 'max:255'],
            'allowed_types' => [Rule::requiredIf($isOrder), 'nullable', 'array', 'min:1'],
            'allowed_types.*' => [Rule::in(LegalBasisVerifier::advocateOrderTypes())],
            'expires_at' => [Rule::requiredIf($isOrder), 'nullable', 'date', 'after_or_equal:today'],
            'purpose' => [Rule::requiredIf($isOrder), 'nullable', 'string', 'min:10', 'max:500'],
        ], [
            'allowed_types.*.in' => "Advokat orderi bu ma'lumot turini ochmaydi (ish haqi, bank siri, chegaradan o'tish) — buning uchun sud qarori kerak.",
            'purpose.required' => "So'rov maqsadini order bo'yicha ish mazmuniga bog'lab qisqacha yozing.",
            'purpose.min' => "So'rov maqsadi kamida 10 belgidan iborat bo'lsin.",
            'allowed_types.required' => "Order qamraydigan ma'lumot turlarini tanlang.",
            'subject_pinfl.required' => "Order subyektining JShShIR'ini kiriting.",
            'expires_at.required' => 'Order amal qilish muddatini kiriting.',
        ]);

        $basis = new LegalBasis([
            'case_id' => $legalCase->id,
            'created_by' => $request->user()->id,
            'type' => $data['type'],
            'number' => $isOrder ? strtoupper(trim($data['number'])) : trim($data['number']),
            'verification_status' => 'pending',
        ]);

        if ($isOrder) {
            $basis->fill([
                'subject_pinfl' => $data['subject_pinfl'],
                'subject_name' => $data['subject_name'] ?? null,
                'allowed_types' => array_values(array_unique($data['allowed_types'])),
                'expires_at' => $data['expires_at'],
                'purpose' => trim($data['purpose']),
                'issued_at' => now(),
            ]);
        }

        if ($request->hasFile('file')) {
            $basis->file_path = $request->file('file')->store("legal-bases/{$legalCase->id}", 'local');
        }

        $basis->save();

        $audit->log(AuditAction::LegalBasisAdded, $basis, [
            'type' => $basis->type->value,
            'number' => $basis->number,
            'has_file' => $basis->file_path !== null,
        ], $legalCase->id);

        $verifier->verify($basis);

        $this->flashResult($basis);

        return back();
    }

    /**
     * "Elektron order berish (demo)": issues an e-order for this case's client in the
     * Advokatlar palatasi registry and returns its number to pre-fill the basis form.
     */
    public function issueOrder(Request $request, LegalCase $legalCase, GovDataProvider $gov, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('addLegalBasis', $legalCase);

        $data = $request->validate([
            'client_pinfl' => ['required', 'string', function ($attr, $value, $fail) {
                if (! Pinfl::isValid($value)) {
                    $fail("JShShIR 14 ta raqamdan iborat bo'lishi kerak.");
                }
            }],
            'client_name' => ['required', 'string', 'max:255'],
            'matter' => ['required', 'string', 'max:255'],
        ], [
            'client_pinfl.required' => "Mijoz (ishonch bildiruvchi) JShShIR'ini kiriting.",
            'matter.required' => 'Ish mazmunini kiriting.',
        ]);

        $licence = $request->user()->advocateProfile?->license_number;

        try {
            $order = $gov->issueAdvocateOrder([
                'license_number' => $licence,
                'client_pinfl' => $data['client_pinfl'],
                'client_name' => trim($data['client_name']),
                'matter' => trim($data['matter']),
            ]);
        } catch (GovApiException $e) {
            throw ValidationException::withMessages(['client_pinfl' => "Elektron order berilmadi: {$e->getMessage()}"]);
        }

        $audit->log(AuditAction::AdvocateOrderIssued, $legalCase, [
            'order_number' => $order['order_number'] ?? null,
            'license' => $licence,
            'client' => Pinfl::mask($data['client_pinfl']),
        ], $legalCase->id);

        Inertia::flash('issued_order', [
            'order_number' => $order['order_number'] ?? null,
            'client_name' => $order['client']['full_name'] ?? $data['client_name'],
            'matter' => $order['matter'] ?? $data['matter'],
            'issued_at' => $order['issued_at'] ?? null,
        ]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Elektron order berildi: {$order['order_number']}"]);

        return back();
    }

    public function verify(Request $request, LegalCase $legalCase, LegalBasis $basis, LegalBasisVerifier $verifier): RedirectResponse
    {
        abort_unless((int) $basis->case_id === (int) $legalCase->id, 404);
        Gate::authorize('addLegalBasis', $legalCase);

        $verifier->verify($basis);
        $this->flashResult($basis);

        return back();
    }

    public function file(Request $request, LegalCase $legalCase, LegalBasis $basis): StreamedResponse
    {
        abort_unless((int) $basis->case_id === (int) $legalCase->id, 404);
        Gate::authorize('view', $legalCase);
        abort_if($basis->file_path === null || ! Storage::disk('local')->exists($basis->file_path), 404);

        return Storage::disk('local')->response($basis->file_path, "asos-{$basis->id}.pdf");
    }

    private function flashResult(LegalBasis $basis): void
    {
        match ($basis->verification_status) {
            'verified' => Inertia::flash('toast', ['type' => 'success', 'message' => "Huquqiy asos tasdiqlandi: {$basis->number}"]),
            'not_found' => Inertia::flash('toast', ['type' => 'error', 'message' => $basis->verification_payload['error'] ?? "Reyestrda {$basis->number} topilmadi"]),
            'expired' => Inertia::flash('toast', ['type' => 'error', 'message' => "Huquqiy asos muddati tugagan: {$basis->number}"]),
            default => Inertia::flash('toast', ['type' => 'error', 'message' => $basis->verification_payload['error'] ?? 'Huquqiy asos tasdiqlanmadi']),
        };
    }
}

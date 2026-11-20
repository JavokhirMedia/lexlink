<?php

namespace App\Http\Controllers\Cases;

use App\Enums\AuditAction;
use App\Enums\LegalBasisType;
use App\Http\Controllers\Controller;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Services\AuditLogger;
use App\Services\LegalBasisVerifier;
use App\Support\Pinfl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'allowed_types.*' => [Rule::in(LegalBasisVerifier::ADVOCATE_ORDER_TYPES)],
            'expires_at' => [Rule::requiredIf($isOrder), 'nullable', 'date', 'after_or_equal:today'],
        ], [
            'allowed_types.*.in' => "Advokat orderi ish haqi (soliq siri) ma'lumotini ochmaydi — buning uchun sud qarori kerak.",
            'allowed_types.required' => "Order qamraydigan ma'lumot turlarini tanlang.",
            'subject_pinfl.required' => "Order subyektining JShShIR'ini kiriting.",
            'expires_at.required' => 'Order amal qilish muddatini kiriting.',
        ]);

        $basis = new LegalBasis([
            'case_id' => $legalCase->id,
            'created_by' => $request->user()->id,
            'type' => $data['type'],
            'number' => trim($data['number']),
            'verification_status' => 'pending',
        ]);

        if ($isOrder) {
            $basis->fill([
                'subject_pinfl' => $data['subject_pinfl'],
                'subject_name' => $data['subject_name'] ?? null,
                'allowed_types' => array_values(array_unique($data['allowed_types'])),
                'expires_at' => $data['expires_at'],
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
            'not_found' => Inertia::flash('toast', ['type' => 'error', 'message' => "Sud reyestrida {$basis->number} raqamli qaror topilmadi."]),
            'expired' => Inertia::flash('toast', ['type' => 'error', 'message' => "Huquqiy asos muddati tugagan: {$basis->number}"]),
            default => Inertia::flash('toast', ['type' => 'error', 'message' => $basis->verification_payload['error'] ?? 'Huquqiy asos tasdiqlanmadi.']),
        };
    }
}

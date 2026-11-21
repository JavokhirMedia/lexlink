<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdvocateProfile;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Services\LicenseChecker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Onboarding after the first OneID login: choose Advokat (licensed) or Yurist (unlicensed).
 * Advocates must prove an ACTIVE licence registered to their own OneID PINFL.
 */
class OnboardingController extends Controller
{
    public function show(Request $request): RedirectResponse
    {
        return $request->user()->role === null
            ? to_route('onboarding.role')
            : to_route('dashboard');
    }

    public function role(Request $request): Response|RedirectResponse
    {
        if ($request->user()->role !== null) {
            return to_route('dashboard');
        }

        return Inertia::render('onboarding/role', [
            'name' => $request->user()->name,
        ]);
    }

    public function storeRole(Request $request, AccountProvisioner $provisioner): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in([UserRole::Advocate->value, UserRole::Lawyer->value])],
        ], ['role.required' => 'Rolni tanlang.', 'role.in' => 'Rolni tanlang.']);

        $user = $request->user();

        if ($user->role !== null) {
            return to_route('dashboard');
        }

        if ($data['role'] === UserRole::Advocate->value) {
            return to_route('onboarding.license');
        }

        DB::transaction(function () use ($user, $provisioner) {
            $user->forceFill(['role' => UserRole::Lawyer])->save();
            $provisioner->provision($user);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Siz yurist sifatida ro\'yxatdan o\'tdingiz']);

        return redirect()->intended(route('dashboard'));
    }

    /** Licence step. Open to not-yet-onboarded users and to lawyers who later obtain a licence. */
    public function license(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdvocate() || $user->isAdmin()) {
            return to_route('dashboard');
        }

        return Inertia::render('onboarding/license', [
            'name' => $user->name,
            'is_lawyer' => $user->isLawyer(),
            'refusal' => $request->session()->get('license_refusal'),
        ]);
    }

    public function storeLicense(Request $request, GovDataProvider $gov, AuditLogger $audit, AccountProvisioner $provisioner): RedirectResponse
    {
        $user = $request->user();

        if ($user->isAdvocate() || $user->isAdmin()) {
            return to_route('dashboard');
        }

        $data = $request->validate([
            'license_number' => ['required', 'string', 'max:32'],
        ], ['license_number.required' => 'Litsenziya raqamini kiriting.']);

        $number = strtoupper(trim($data['license_number']));

        try {
            $record = $gov->advocateLicense($number);
        } catch (GovApiException) {
            throw ValidationException::withMessages([
                'license_number' => 'Advokatlar reestri bilan bog\'lanib bo\'lmadi. Birozdan so\'ng urinib ko\'ring.',
            ]);
        }

        if ($record === null) {
            throw ValidationException::withMessages([
                'license_number' => 'Bunday raqamli litsenziya advokatlar reestrida topilmadi.',
            ]);
        }

        if (! LicenseChecker::pinflMatches($record, $user)) {
            throw ValidationException::withMessages([
                'license_number' => 'Litsenziya boshqa shaxsga tegishli',
            ]);
        }

        $status = (string) ($record['status'] ?? '');

        if ($status !== 'active') {
            return to_route('onboarding.license')->with('license_refusal', [
                'license_number' => $number,
                'status' => $status,
                'status_label' => $status === 'suspended' ? "to'xtatib turilgan" : 'bekor qilingan',
            ]);
        }

        DB::transaction(function () use ($user, $number, $record, $audit, $provisioner) {
            $profile = AdvocateProfile::updateOrCreate(['user_id' => $user->id], [
                'license_number' => $number,
                'license_status' => 'active',
                'bureau_name' => $record['bureau_name'] ?? null,
                'verified_at' => now(),
                'last_checked_at' => now(),
            ]);

            $user->forceFill(['role' => UserRole::Advocate])->save();
            $provisioner->provision($user);

            $audit->log(AuditAction::LicenseVerified, $profile, [
                'license_number' => $number,
                'bureau_name' => $record['bureau_name'] ?? null,
            ], user: $user);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Litsenziya tasdiqlandi — siz advokat sifatida ro\'yxatdan o\'tdingiz']);

        return redirect()->intended(route('dashboard'));
    }
}

<?php

namespace App\Http\Controllers\Cases;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AdvocateLawyerLink;
use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class CaseMemberController extends Controller
{
    /** Advocate adds a lawyer from their accepted team links. */
    public function store(Request $request, LegalCase $legalCase, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manageMembers', $legalCase);

        $data = $request->validate(['user_id' => ['required', 'integer']]);

        $linked = AdvocateLawyerLink::query()
            ->where('advocate_id', $legalCase->owner_id)
            ->where('lawyer_id', $data['user_id'])
            ->where('status', 'accepted')
            ->exists();

        if (! $linked) {
            return back()->withErrors(['user_id' => 'Bu yurist sizning jamoangizda emas.']);
        }

        $member = CaseMember::firstOrCreate(
            ['case_id' => $legalCase->id, 'user_id' => $data['user_id']],
            ['role' => 'lawyer', 'can_view_responses' => false],
        );

        $audit->log(AuditAction::PermissionChanged, $member, [
            'change' => 'member_added',
            'member_user_id' => $member->user_id,
            'member_name' => $member->user->name,
        ], $legalCase->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Yurist ishga qo'shildi."]);

        return back();
    }

    /** Toggle "Javoblarni ko'rish ruxsati" for a lawyer member. */
    public function update(Request $request, LegalCase $legalCase, CaseMember $member, AuditLogger $audit): RedirectResponse
    {
        abort_unless((int) $member->case_id === (int) $legalCase->id, 404);
        Gate::authorize('manageMembers', $legalCase);
        abort_if($member->role === 'advocate', 422, "Advokat ruxsatini o'zgartirib bo'lmaydi.");

        $data = $request->validate(['can_view_responses' => ['required', 'boolean']]);

        $old = $member->can_view_responses;
        $member->update(['can_view_responses' => $data['can_view_responses']]);

        $audit->log(AuditAction::PermissionChanged, $member, [
            'change' => 'can_view_responses',
            'member_user_id' => $member->user_id,
            'member_name' => $member->user->name,
            'from' => $old,
            'to' => $member->can_view_responses,
        ], $legalCase->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => $member->can_view_responses ? 'Javoblarni ko\'rish ruxsati berildi.' : 'Javoblarni ko\'rish ruxsati olib tashlandi.']);

        return back();
    }

    public function destroy(Request $request, LegalCase $legalCase, CaseMember $member, AuditLogger $audit): RedirectResponse
    {
        abort_unless((int) $member->case_id === (int) $legalCase->id, 404);
        Gate::authorize('manageMembers', $legalCase);
        abort_if($member->role === 'advocate', 422, "Ish egasini olib tashlab bo'lmaydi.");

        $audit->log(AuditAction::PermissionChanged, $member, [
            'change' => 'member_removed',
            'member_user_id' => $member->user_id,
            'member_name' => $member->user->name,
        ], $legalCase->id);

        $member->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Yurist ishdan chiqarildi.']);

        return back();
    }
}

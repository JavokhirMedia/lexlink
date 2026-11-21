<?php

namespace App\Http\Controllers\Team;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AdvocateLawyerLink;
use App\Models\User;
use App\Services\AccountProvisioner;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** A lawyer opens /invites/{token} (after OneID login) and joins the advocate's team. */
class InviteController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $link = AdvocateLawyerLink::with('advocate.advocateProfile')->where('token', $token)->first();
        $reason = $link ? $this->refusalReason($link, $request->user()) : 'Taklif havolasi topilmadi.';

        return Inertia::render('invites/accept', [
            'token' => $token,
            'invite' => $link ? [
                'advocate_name' => $link->advocate->name,
                'bureau_name' => $link->advocate->advocateProfile?->bureau_name,
                'license_number' => $link->advocate->advocateProfile?->license_number,
                'contact' => $link->invite_contact,
                'status' => $link->status,
                'status_label' => TeamController::STATUS_LABELS[$link->status] ?? $link->status,
            ] : null,
            'already_member' => $link !== null && $link->status === 'accepted' && $link->lawyer_id === $request->user()->id,
            'can_accept' => $reason === null,
            'reason' => $reason,
            'will_become_lawyer' => $request->user()->role === null,
        ]);
    }

    public function accept(Request $request, string $token, AuditLogger $audit, AccountProvisioner $provisioner): RedirectResponse
    {
        $user = $request->user();
        $link = AdvocateLawyerLink::where('token', $token)->firstOrFail();

        if ($reason = $this->refusalReason($link, $user)) {
            return back()->withErrors(['invite' => $reason]);
        }

        DB::transaction(function () use ($link, $user, $audit, $provisioner) {
            if ($user->role === null) {
                $user->forceFill(['role' => UserRole::Lawyer])->save();
                $provisioner->provision($user);
            }

            $link->update(['lawyer_id' => $user->id, 'status' => 'accepted', 'accepted_at' => now()]);

            $audit->log(AuditAction::LawyerJoined, $link, [
                'advocate_id' => $link->advocate_id,
                'advocate_name' => $link->advocate?->name,
            ], user: $user);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Siz advokat jamoasiga qo\'shildingiz']);

        return to_route('team.index');
    }

    private function refusalReason(AdvocateLawyerLink $link, User $user): ?string
    {
        return match (true) {
            $link->status === 'revoked' => 'Bu taklif bekor qilingan.',
            $link->status === 'accepted' && $link->lawyer_id === $user->id => 'Siz allaqachon ushbu advokat jamoasidasiz.',
            $link->status !== 'pending' => 'Bu taklif allaqachon boshqa foydalanuvchi tomonidan qabul qilingan.',
            $link->advocate_id === $user->id => "O'zingizni jamoaga taklif qila olmaysiz.",
            $user->isAdvocate(), $user->isAdmin() => 'Taklifni faqat yurist (litsenziyasiz) qabul qilishi mumkin.',
            AdvocateLawyerLink::where('advocate_id', $link->advocate_id)->where('lawyer_id', $user->id)->where('status', 'accepted')->exists() => 'Siz allaqachon ushbu advokat jamoasidasiz.',
            default => null,
        };
    }
}

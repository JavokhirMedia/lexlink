<?php

namespace App\Http\Controllers\Team;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AdvocateLawyerLink;
use App\Models\CaseMember;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Advocate's team: invite lawyers (unlicensed assistants) by e-mail or phone, revoke them.
 * Lawyers see the advocates they work with (a lawyer may work with several advocates).
 */
class TeamController extends Controller
{
    public const STATUS_LABELS = [
        'pending' => 'Kutilmoqda',
        'accepted' => 'Jamoada',
        'revoked' => 'Bekor qilingan',
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAdvocate()) {
            $links = $user->lawyerLinks()->with('lawyer')->latest('id')->get()
                ->map(fn (AdvocateLawyerLink $link) => [
                    'id' => $link->id,
                    'contact' => $link->invite_contact,
                    'status' => $link->status,
                    'status_label' => self::STATUS_LABELS[$link->status] ?? $link->status,
                    'person' => $link->lawyer ? ['id' => $link->lawyer->id, 'name' => $link->lawyer->name, 'phone' => $link->lawyer->phone] : null,
                    'invite_url' => $link->status === 'pending' ? route('invites.show', $link->token) : null,
                    'invited_at' => $link->invited_at?->toIso8601String(),
                    'accepted_at' => $link->accepted_at?->toIso8601String(),
                ]);
        } else {
            $links = $user->advocateLinks()->with('advocate.advocateProfile')->where('status', 'accepted')->latest('id')->get()
                ->map(fn (AdvocateLawyerLink $link) => [
                    'id' => $link->id,
                    'contact' => $link->invite_contact,
                    'status' => $link->status,
                    'status_label' => self::STATUS_LABELS[$link->status] ?? $link->status,
                    'person' => $link->advocate ? [
                        'id' => $link->advocate->id,
                        'name' => $link->advocate->name,
                        'phone' => $link->advocate->phone,
                        'bureau_name' => $link->advocate->advocateProfile?->bureau_name,
                    ] : null,
                    'invite_url' => null,
                    'invited_at' => $link->invited_at?->toIso8601String(),
                    'accepted_at' => $link->accepted_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('team/index', [
            'mode' => $user->isAdvocate() ? 'advocate' : 'lawyer',
            'links' => $links,
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $advocate = $request->user();

        $data = $request->validate([
            'contact' => ['required', 'string', 'max:255'],
        ], ['contact.required' => 'Elektron pochta yoki telefon raqamini kiriting.']);

        $contact = $this->normalizeContact($data['contact']);

        $link = $advocate->lawyerLinks()->where('invite_contact', $contact)->where('status', 'pending')->first();

        if ($link === null) {
            $link = $advocate->lawyerLinks()->create([
                'invite_contact' => $contact,
                'token' => Str::random(48),
                'status' => 'pending',
                'invited_at' => now(),
            ]);

            $audit->log(AuditAction::LawyerInvited, $link, ['contact' => $contact]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Taklif havolasi yaratildi — uni yuristga yuboring']);
        Inertia::flash('invite_url', route('invites.show', $link->token));

        return to_route('team.index');
    }

    public function destroy(Request $request, AdvocateLawyerLink $link, AuditLogger $audit): RedirectResponse
    {
        abort_unless($link->advocate_id === $request->user()->id, 403);

        if ($link->status !== 'revoked') {
            DB::transaction(function () use ($link, $audit, $request) {
                $removed = 0;

                if ($link->lawyer_id !== null) {
                    $removed = CaseMember::where('user_id', $link->lawyer_id)
                        ->where('role', 'lawyer')
                        ->whereIn('case_id', $request->user()->ownedCases()->select('id'))
                        ->delete();
                }

                $link->update(['status' => 'revoked']);

                $audit->log(AuditAction::PermissionChanged, $link, [
                    'change' => 'team_link_revoked',
                    'lawyer_id' => $link->lawyer_id,
                    'contact' => $link->invite_contact,
                    'removed_from_cases' => $removed,
                ]);
            });
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Yurist jamoadan chiqarildi']);

        return to_route('team.index');
    }

    private function normalizeContact(string $raw): string
    {
        $raw = trim($raw);

        if (filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return Str::lower($raw);
        }

        $digits = preg_replace('/\D+/', '', $raw);

        if (strlen($digits) === 9) {
            $digits = '998'.$digits;
        }

        if (! preg_match('/^998\d{9}$/', $digits)) {
            throw ValidationException::withMessages([
                'contact' => "Elektron pochta yoki telefon raqami noto'g'ri (masalan: +998 90 123 45 67).",
            ]);
        }

        return '+'.$digits;
    }
}

<?php

namespace App\Policies;

use App\Enums\DataRequestStatus;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Abilities:
 *  - view           : case member (metadata) or admin (metadata only)
 *  - update         : case member while draft / awaiting signature
 *  - requestSignature: case member while draft
 *  - sign           : advocate member with an active licence
 *  - viewResponse   : delegated to LegalCasePolicy::viewResponses (rule #3)
 */
class DataRequestPolicy
{
    public function view(User $user, DataRequest $r): bool
    {
        return $user->isAdmin() || $this->membership($user, $r->legalCase) !== null;
    }

    public function update(User $user, DataRequest $r): bool
    {
        return ! $user->isAdmin()
            && $this->membership($user, $r->legalCase) !== null
            && in_array($r->status, [DataRequestStatus::Draft, DataRequestStatus::AwaitingSignature], true);
    }

    public function requestSignature(User $user, DataRequest $r): bool
    {
        return $r->status === DataRequestStatus::Draft && $this->update($user, $r);
    }

    public function sign(User $user, DataRequest $r): bool
    {
        return $user->hasActiveLicense()
            && $this->membership($user, $r->legalCase)?->role === 'advocate'
            && in_array($r->status, [DataRequestStatus::Draft, DataRequestStatus::AwaitingSignature], true);
    }

    public function viewResponse(User $user, DataRequest $r): bool
    {
        return self::canViewResponses($user, $r->legalCase);
    }

    public static function canViewResponses(User $user, LegalCase $case): bool
    {
        if (class_exists(LegalCasePolicy::class) && method_exists(LegalCasePolicy::class, 'viewResponses')) {
            $res = app(LegalCasePolicy::class)->viewResponses($user, $case);

            return $res instanceof Response ? $res->allowed() : (bool) $res;
        }

        if ($user->isAdmin()) {
            return false;
        }
        $m = $case->membershipOf($user);
        if (! $m) {
            return false;
        }
        if ($m->role === 'advocate' && $user->isAdvocate()) {
            return true;
        }

        return $user->isLawyer() && $m->can_view_responses && $m->secrecy_declared_at !== null;
    }

    /** Lawyer has the permission but has not yet signed the per-case secrecy declaration. */
    public static function needsSecrecyDeclaration(User $user, LegalCase $case): bool
    {
        $m = $case->membershipOf($user);

        return $user->isLawyer() && $m && $m->can_view_responses && $m->secrecy_declared_at === null;
    }

    private function membership(User $user, ?LegalCase $case): ?CaseMember
    {
        return $case?->membershipOf($user);
    }
}

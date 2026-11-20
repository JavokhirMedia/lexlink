<?php

namespace App\Policies;

use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\User;

/**
 * Abilities:
 *  - view            : any case member
 *  - update          : owner advocate
 *  - manageMembers   : owner advocate (add lawyers, toggle can_view_responses)
 *  - createRequest   : any case member (lawyers prepare drafts)
 *  - addLegalBasis   : advocate member with an active licence
 *  - sign            : advocate member with an active licence (sign & submit requests)
 *  - viewResponses   : advocate member => true; lawyer => can_view_responses AND secrecy declared; admin => false
 */
class LegalCasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdvocate() || $user->isLawyer();
    }

    public function create(User $user): bool
    {
        return $user->isAdvocate() || $user->isLawyer();
    }

    public function view(User $user, LegalCase $case): bool
    {
        return $this->membership($user, $case) !== null;
    }

    public function update(User $user, LegalCase $case): bool
    {
        return $user->isAdvocate()
            && $case->owner_id === $user->id
            && $this->membership($user, $case)?->role === 'advocate';
    }

    public function manageMembers(User $user, LegalCase $case): bool
    {
        return $this->update($user, $case);
    }

    public function createRequest(User $user, LegalCase $case): bool
    {
        return ! $user->isAdmin() && $this->membership($user, $case) !== null;
    }

    public function addLegalBasis(User $user, LegalCase $case): bool
    {
        return $this->isLicensedAdvocateMember($user, $case);
    }

    public function sign(User $user, LegalCase $case): bool
    {
        return $this->isLicensedAdvocateMember($user, $case);
    }

    public function viewResponses(User $user, LegalCase $case): bool
    {
        if ($user->isAdmin()) {
            return false; // admin sees metadata only, never payload
        }

        $m = $this->membership($user, $case);
        if ($m === null) {
            return false;
        }

        if ($m->role === 'advocate' && $user->isAdvocate()) {
            return true;
        }

        return $user->isLawyer() && $m->can_view_responses && $m->secrecy_declared_at !== null;
    }

    private function isLicensedAdvocateMember(User $user, LegalCase $case): bool
    {
        return $user->hasActiveLicense() && $this->membership($user, $case)?->role === 'advocate';
    }

    private function membership(User $user, LegalCase $case): ?CaseMember
    {
        if ($case->relationLoaded('memberships')) {
            return $case->memberships->firstWhere('user_id', $user->id);
        }

        return $case->membershipOf($user);
    }
}

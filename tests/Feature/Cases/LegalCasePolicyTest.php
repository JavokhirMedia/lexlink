<?php

namespace Tests\Feature\Cases;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class LegalCasePolicyTest extends TestCase
{
    use CasesTestHelpers, RefreshDatabase;

    public function test_policy_matrix(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $outsider = $this->advocate();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $case = $this->caseFor($adv, $lawyer);

        $this->assertTrue(Gate::forUser($adv)->allows('view', $case));
        $this->assertTrue(Gate::forUser($lawyer)->allows('view', $case));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $case));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $case));

        $this->assertTrue(Gate::forUser($adv)->allows('update', $case));
        $this->assertTrue(Gate::forUser($adv)->allows('manageMembers', $case));
        $this->assertFalse(Gate::forUser($lawyer)->allows('manageMembers', $case));
        $this->assertFalse(Gate::forUser($outsider)->allows('update', $case));

        $this->assertTrue(Gate::forUser($adv)->allows('createRequest', $case));
        $this->assertTrue(Gate::forUser($lawyer)->allows('createRequest', $case));
        $this->assertFalse(Gate::forUser($outsider)->allows('createRequest', $case));

        $this->assertTrue(Gate::forUser($adv)->allows('addLegalBasis', $case));
        $this->assertFalse(Gate::forUser($lawyer)->allows('addLegalBasis', $case));
        $this->assertTrue(Gate::forUser($adv)->allows('sign', $case));
        $this->assertFalse(Gate::forUser($lawyer)->allows('sign', $case));
    }

    public function test_revoked_licence_removes_basis_and_sign_rights(): void
    {
        $adv = $this->advocate('revoked');
        $case = $this->caseFor($adv);

        $this->assertTrue(Gate::forUser($adv)->allows('view', $case));
        $this->assertFalse(Gate::forUser($adv)->allows('addLegalBasis', $case));
        $this->assertFalse(Gate::forUser($adv)->allows('sign', $case));
    }

    public function test_view_responses_rules(): void
    {
        $adv = $this->advocate();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $noPermission = $this->lawyer();
        $permNoSecrecy = $this->lawyer();
        $secrecyNoPerm = $this->lawyer();
        $full = $this->lawyer();

        $case = $this->caseFor($adv);
        $case->memberships()->create(['user_id' => $noPermission->id, 'role' => 'lawyer']);
        $case->memberships()->create(['user_id' => $permNoSecrecy->id, 'role' => 'lawyer', 'can_view_responses' => true]);
        $case->memberships()->create(['user_id' => $secrecyNoPerm->id, 'role' => 'lawyer', 'secrecy_declared_at' => now()]);
        $case->memberships()->create(['user_id' => $full->id, 'role' => 'lawyer', 'can_view_responses' => true, 'secrecy_declared_at' => now()]);
        $case->memberships()->create(['user_id' => $admin->id, 'role' => 'lawyer', 'can_view_responses' => true, 'secrecy_declared_at' => now()]);

        $this->assertTrue(Gate::forUser($adv)->allows('viewResponses', $case));
        $this->assertFalse(Gate::forUser($noPermission)->allows('viewResponses', $case));
        $this->assertFalse(Gate::forUser($permNoSecrecy)->allows('viewResponses', $case));
        $this->assertFalse(Gate::forUser($secrecyNoPerm)->allows('viewResponses', $case));
        $this->assertTrue(Gate::forUser($full)->allows('viewResponses', $case));
        $this->assertFalse(Gate::forUser($admin)->allows('viewResponses', $case));
        $this->assertFalse(Gate::forUser($this->advocate())->allows('viewResponses', $case));
    }

    public function test_non_member_cannot_open_case_page(): void
    {
        $case = $this->caseFor($this->advocate());

        $this->actingAs($this->advocate())->get(route('cases.show', $case))->assertForbidden();
    }
}

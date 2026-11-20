<?php

namespace Tests\Feature\Cases;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CaseMembersTest extends TestCase
{
    use CasesTestHelpers, RefreshDatabase;

    public function test_advocate_toggles_view_responses_and_it_is_audited(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $case = $this->caseFor($adv, $lawyer, ['secrecy_declared_at' => now()]);
        $member = $case->membershipOf($lawyer);

        $this->assertFalse(Gate::forUser($lawyer)->allows('viewResponses', $case->fresh()));

        $this->actingAs($adv)->patch(route('cases.members.update', [$case, $member]), ['can_view_responses' => true])->assertRedirect();

        $this->assertTrue($member->fresh()->can_view_responses);
        $this->assertTrue(Gate::forUser($lawyer)->allows('viewResponses', $case->fresh()));
        $log = AuditLog::where('action', AuditAction::PermissionChanged)->latest('id')->firstOrFail();
        $this->assertSame($case->id, $log->case_id);
        $this->assertSame(true, $log->meta['to']);

        $this->actingAs($adv)->patch(route('cases.members.update', [$case, $member]), ['can_view_responses' => false]);
        $this->assertFalse($member->fresh()->can_view_responses);
    }

    public function test_lawyer_cannot_toggle_permissions(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $case = $this->caseFor($adv, $lawyer);

        $this->actingAs($lawyer)->patch(route('cases.members.update', [$case, $case->membershipOf($lawyer)]), ['can_view_responses' => true])->assertForbidden();
        $this->assertFalse($case->membershipOf($lawyer)->can_view_responses);
    }

    public function test_add_member_only_from_accepted_team(): void
    {
        $adv = $this->advocate();
        $teammate = $this->lawyer();
        $stranger = $this->lawyer();
        $this->link($adv, $teammate);
        $case = $this->caseFor($adv);

        $this->actingAs($adv)->post(route('cases.members.store', $case), ['user_id' => $stranger->id])->assertSessionHasErrors('user_id');
        $this->actingAs($adv)->post(route('cases.members.store', $case), ['user_id' => $teammate->id])->assertSessionHasNoErrors();

        $this->assertSame('lawyer', $case->membershipOf($teammate)->role);
        $this->assertNull($case->membershipOf($stranger));
    }
}

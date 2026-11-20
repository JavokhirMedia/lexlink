<?php

namespace Tests\Feature\Billing;

use App\Models\AdvocateLawyerLink;
use App\Models\CaseMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use BillingTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedPlans();
        $this->withoutVite();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('dashboard'))->assertRedirect();
    }

    public function test_dashboard_renders_for_advocate(): void
    {
        $u = $this->advocate(balance: 75_000, used: 3);
        $this->dataRequest($u, ['status' => 'awaiting_signature']);
        $this->dataRequest($u, ['status' => 'processing']);
        $this->dataRequest($u, ['status' => 'completed']);

        $this->actingAs($u)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'advocate')
                ->where('kpi.active_cases', 3)
                ->where('kpi.awaiting_signature', 1)
                ->where('kpi.in_flight', 1)
                ->where('kpi.used_requests', 3)
                ->where('kpi.included_requests', 10)
                ->where('kpi.balance', 75_000)
                ->has('awaiting_signature', 1)
                ->has('recent', 1)
                ->where('recent.0.status', 'completed')
                ->missing('recent.0.subject_pinfl')
                ->missing('my_drafts'));
    }

    public function test_dashboard_renders_for_lawyer(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $r = $this->dataRequest($adv, ['status' => 'draft', 'created_by' => $lawyer->id]);
        CaseMember::create(['case_id' => $r->case_id, 'user_id' => $lawyer->id, 'role' => 'lawyer']);
        AdvocateLawyerLink::create(['advocate_id' => $adv->id, 'lawyer_id' => $lawyer->id, 'invite_contact' => 'x@example.uz', 'token' => str_repeat('a', 40), 'status' => 'accepted']);
        $this->dataRequest($adv); // another case the lawyer is not in

        $this->actingAs($lawyer)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('role', 'lawyer')
                ->where('kpi.active_cases', 1)
                ->has('my_drafts', 1)
                ->has('advocates', 1)
                ->where('advocates.0.name', $adv->name)
                ->has('active_cases', 1)
                ->missing('awaiting_signature'));
    }
}

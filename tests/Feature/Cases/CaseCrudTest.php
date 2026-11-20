<?php

namespace Tests\Feature\Cases;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\LegalCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CaseCrudTest extends TestCase
{
    use CasesTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeGov();
        $this->withoutVite();
    }

    public function test_advocate_creates_case_and_becomes_owner_member(): void
    {
        $adv = $this->advocate();

        $this->actingAs($adv)->post(route('cases.store'), [
            'title' => 'Aliment undirish',
            'client_name' => 'Karimova Dilnoza',
            'category' => 'aliment',
            'parties' => [['role' => 'Javobgar', 'name' => 'Karimov Aziz']],
        ])->assertRedirect();

        $case = LegalCase::firstOrFail();
        $this->assertSame($adv->id, $case->owner_id);
        $this->assertSame('advocate', $case->membershipOf($adv)->role);
        $this->assertTrue(AuditLog::where('action', AuditAction::CaseCreated)->where('case_id', $case->id)->exists());
    }

    public function test_lawyer_case_is_owned_by_linked_advocate(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $this->link($adv, $lawyer);

        $this->actingAs($lawyer)->post(route('cases.store'), [
            'title' => 'Mehnat nizosi',
            'client_name' => 'Aliyev Bobur',
            'category' => 'mehnat',
            'advocate_id' => $adv->id,
        ])->assertRedirect();

        $case = LegalCase::firstOrFail();
        $this->assertSame($adv->id, $case->owner_id);
        $this->assertSame('advocate', $case->membershipOf($adv)->role);
        $this->assertSame('lawyer', $case->membershipOf($lawyer)->role);
        $this->assertFalse($case->membershipOf($lawyer)->can_view_responses);
    }

    public function test_lawyer_cannot_attach_case_to_unlinked_advocate(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $this->link($adv, $lawyer, 'pending');

        $this->actingAs($lawyer)->post(route('cases.store'), [
            'title' => 'X', 'client_name' => 'Y', 'category' => 'boshqa', 'advocate_id' => $adv->id,
        ])->assertSessionHasErrors('advocate_id');

        $this->actingAs($lawyer)->post(route('cases.store'), [
            'title' => 'X', 'client_name' => 'Y', 'category' => 'boshqa',
        ])->assertSessionHasErrors('advocate_id');

        $this->assertSame(0, LegalCase::count());
    }

    public function test_index_lists_only_member_cases_with_search(): void
    {
        $adv = $this->advocate();
        $mine = $this->caseFor($adv);
        $this->caseFor($this->advocate());

        $this->actingAs($adv)->get(route('cases.index'))
            ->assertInertia(fn (Assert $p) => $p->component('cases/index')->has('cases', 1)->where('cases.0.id', $mine->id));

        $this->actingAs($adv)->get(route('cases.index', ['search' => 'nomavjud']))
            ->assertInertia(fn (Assert $p) => $p->has('cases', 0));
    }

    public function test_show_page_props(): void
    {
        $adv = $this->advocate();
        $case = $this->caseFor($adv);

        $this->actingAs($adv)->get(route('cases.show', $case))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('cases/show')
                ->where('case.id', $case->id)
                ->where('membership.role', 'advocate')
                ->where('can.sign', true)
                ->where('can.manage_members', true)
                ->where('can.create_request', true)
                ->has('requests', 0)
                ->has('bases', 0)
                ->has('coverage.salary', 0)
                ->has('members', 1));
    }
}

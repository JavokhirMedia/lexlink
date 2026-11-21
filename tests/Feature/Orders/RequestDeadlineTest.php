<?php

namespace Tests\Feature\Orders;

use App\Enums\DataRequestStatus;
use App\Enums\LegalBasisType;
use App\Http\Controllers\Cases\CasePresenter;
use App\Jobs\FetchGovResultJob;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\Mock\MockCourtCase;
use App\Models\Plan;
use App\Models\User;
use App\Services\DataRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Advokatura to'g'risidagi Qonun 7¹-modda: 15 kun, or 3 ish kuni once proceedings have started. */
class RequestDeadlineTest extends TestCase
{
    use OrdersTestHelpers;
    use RefreshDatabase;

    private User $adv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootOrders();
        Plan::create(['code' => 'start', 'name' => 'Start', 'audience' => 'Yurist', 'price' => 0, 'included_requests' => 10, 'extra_price' => 15000, 'included_ai' => 0]);
        $this->adv = $this->advocateUser();
        // Thursday
        $this->travelTo(Carbon::parse('2026-11-19 10:00:00'));
    }

    private function basis(LegalCase $case, LegalBasisType $type): LegalBasis
    {
        return LegalBasis::create([
            'case_id' => $case->id, 'created_by' => $this->adv->id, 'type' => $type,
            'number' => $type === LegalBasisType::AdvocateOrder ? 'ORD-2026-000457' : '2-1001-2611/0457',
            'issued_at' => now()->subDays(5), 'expires_at' => now()->addDays(60),
            'subject_pinfl' => self::SUBJECT_PINFL, 'subject_name' => 'Karimov Aziz',
            'allowed_types' => ['property', 'vehicle'], 'verification_status' => 'verified', 'verified_at' => now(),
        ]);
    }

    private function submit(LegalCase $case): DataRequest
    {
        $svc = app(DataRequestService::class);
        $r = $svc->create($case, $this->adv, [
            'type' => 'vehicle', 'subject_pinfl' => self::SUBJECT_PINFL, 'subject_name' => 'Karimov Aziz',
            'purpose' => 'Mol-mulkni aniqlash', 'request_text' => "Rasmiy so'rov",
        ]);
        $this->actingAs($this->adv)->post(route('requests.sign', $r), ['key_id' => 'demo-eri', 'confirm' => true])->assertSessionHasNoErrors();

        return $r->fresh();
    }

    public function test_advocate_order_without_proceedings_gets_15_calendar_days(): void
    {
        $case = $this->caseOf($this->adv, '2-9999-0000/0001'); // not in the court registry
        $this->basis($case, LegalBasisType::AdvocateOrder);

        $r = $this->submit($case);

        $this->assertNotNull($r->submitted_at);
        $this->assertSame('default', $r->deadline_basis);
        $this->assertSame('2026-12-04', $r->due_at->toDateString());
    }

    public function test_advocate_order_with_started_court_proceedings_gets_3_working_days(): void
    {
        MockCourtCase::create([
            'case_number' => '2-1001-2610/0398', 'court' => 'Yunusobod sudi', 'judge' => 'Ergasheva M. T.',
            'category' => 'Aliment', 'status' => 'Ish yuritishda', 'parties' => [], 'hearings' => [], 'decisions' => [],
        ]);
        $case = $this->caseOf($this->adv, '2-1001-2610/0398');
        $this->basis($case, LegalBasisType::AdvocateOrder);

        $r = $this->submit($case);

        $this->assertSame('proceedings', $r->deadline_basis);
        // Thu 19.11 + 3 working days (Sat/Sun skipped) = Tue 24.11
        $this->assertSame('2026-11-24', $r->due_at->toDateString());
        $this->assertStringContainsString('3 ish kuni', CasePresenter::request($r)['deadline_label']);
    }

    public function test_court_decision_basis_gets_default_15_days(): void
    {
        $case = $this->caseOf($this->adv, '2-1001-2610/0398');
        $this->basis($case, LegalBasisType::CourtDecision);

        $r = $this->submit($case);

        $this->assertSame('court_decision', $r->deadline_basis);
        $this->assertSame('2026-12-04', $r->due_at->toDateString());
    }

    public function test_answered_on_time_flag(): void
    {
        $case = $this->caseOf($this->adv);
        $this->basis($case, LegalBasisType::AdvocateOrder);
        $r = $this->submit($case);

        $this->travel(5)->seconds();
        FetchGovResultJob::dispatchSync($r->id);
        $r->refresh();

        $this->assertSame(DataRequestStatus::Completed, $r->status);
        $this->assertTrue(CasePresenter::request($r)['answered_on_time']);

        $r->forceFill(['completed_at' => $r->due_at->copy()->addDay()])->save();
        $this->assertFalse(CasePresenter::request($r->fresh())['answered_on_time']);
    }
}

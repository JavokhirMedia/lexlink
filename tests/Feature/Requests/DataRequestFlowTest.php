<?php

namespace Tests\Feature\Requests;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\LegalBasisType;
use App\Jobs\FetchGovResultJob;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\Plan;
use App\Models\RequestResponse;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\AiService;
use App\Services\DataRequestService;
use App\Services\Gov\GovDataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    private const SUBJECT = '31234567890112';

    private FakeGovProvider $gov;

    private FakeAiService $ai;

    private User $advocate;

    private User $lawyer;

    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gov = new FakeGovProvider;
        $this->ai = new FakeAiService;
        $this->app->instance(GovDataProvider::class, $this->gov);
        $this->app->instance(AiService::class, $this->ai);
        Storage::fake('local');
        $this->withoutVite();

        Plan::create(['code' => 'start', 'name' => 'Start', 'audience' => 'Yurist', 'price' => 0, 'included_requests' => 1, 'extra_price' => 15000, 'included_ai' => 0]);

        $this->advocate = User::factory()->advocate('active', 'ADV-00001')->create(['name' => 'Akmal Advokatov']);
        $this->lawyer = User::factory()->lawyer()->create(['name' => 'Lola Yuristova']);

        $this->case = LegalCase::create(['owner_id' => $this->advocate->id, 'title' => 'Aliment undirish', 'client_name' => 'Demo mijoz']);
        CaseMember::create(['case_id' => $this->case->id, 'user_id' => $this->advocate->id, 'role' => 'advocate', 'can_view_responses' => true]);
        CaseMember::create(['case_id' => $this->case->id, 'user_id' => $this->lawyer->id, 'role' => 'lawyer', 'can_view_responses' => true]);
    }

    private function basis(array $attrs = []): LegalBasis
    {
        return LegalBasis::create(array_merge([
            'case_id' => $this->case->id,
            'created_by' => $this->advocate->id,
            'type' => LegalBasisType::CourtDecision,
            'number' => '2-1001-2611/0457',
            'court_name' => 'Toshkent tumanlararo sudi',
            'issued_at' => now()->subDays(5),
            'expires_at' => now()->addDays(30),
            'subject_pinfl' => self::SUBJECT,
            'subject_name' => 'Demo Shaxs',
            'allowed_types' => ['salary', 'property'],
            'verification_status' => 'verified',
            'verified_at' => now(),
        ], $attrs));
    }

    private function draft(string $type, ?User $by = null, array $params = []): DataRequest
    {
        return app(DataRequestService::class)->create($this->case, $by ?? $this->advocate, [
            'type' => $type,
            'subject_pinfl' => self::SUBJECT,
            'subject_name' => 'Demo Shaxs',
            'params' => $params ?: ['period_from' => '2026-05', 'period_to' => '2026-07'],
            'purpose' => 'Aliment miqdorini aniqlash',
            'request_text' => "Rasmiy so'rov matni",
        ]);
    }

    private function sign(DataRequest $r, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->advocate)
            ->from(route('requests.show', $r))
            ->post(route('requests.sign', $r), ['key_id' => 'demo-eri', 'confirm' => true]);
    }

    public function test_blocked_when_basis_does_not_cover_vehicle(): void
    {
        $this->basis();
        $r = $this->draft('vehicle');

        $check = app(DataRequestService::class)->checkBasis($r);
        $this->assertFalse($check['ok']);
        $this->assertSame("Sud ajrimi 2-1001-2611/0457 avtotransport ma'lumotini qamramaydi", $check['reason']);

        $this->sign($r)->assertSessionHasErrors(['basis' => $check['reason']]);

        $this->assertSame(DataRequestStatus::Draft, $r->fresh()->status);
        $this->assertSame(0, $this->gov->submitCalls);
        $this->assertTrue(AuditLog::where('action', AuditAction::RequestBlocked)->where('subject_id', $r->id)->exists());
    }

    public function test_salary_with_advocate_order_is_blocked(): void
    {
        $this->basis(['type' => LegalBasisType::AdvocateOrder, 'number' => 'ORD-77', 'court_name' => null, 'allowed_types' => ['property', 'vehicle', 'court_case']]);
        $r = $this->draft('salary');

        $this->sign($r)->assertSessionHasErrors('basis');

        $this->assertStringContainsString('Soliq siri — faqat sud qarori', app(DataRequestService::class)->checkBasis($r)['reason']);
        $this->assertSame(DataRequestStatus::Draft, $r->fresh()->status);
        $this->assertTrue(AuditLog::where('action', AuditAction::RequestBlocked)->exists());
    }

    public function test_other_block_reasons(): void
    {
        $svc = app(DataRequestService::class);
        $b = $this->basis(['subject_pinfl' => '39999999999999']);
        $this->assertSame("Asosdagi shaxs JShShIR so'rovdagi shaxsga mos emas", $svc->checkBasis($this->draft('salary'))['reason']);

        $b->update(['subject_pinfl' => self::SUBJECT, 'expires_at' => now()->subDay()]);
        $this->assertStringStartsWith("Asos muddati o'tgan", $svc->checkBasis($this->draft('salary'))['reason']);

        $b->update(['expires_at' => now()->addDays(3)]);
        $this->assertTrue($svc->checkBasis($this->draft('salary'))['ok']);
    }

    public function test_lawyer_cannot_sign_but_can_send_for_signature(): void
    {
        $this->basis();
        $r = $this->draft('salary', $this->lawyer);

        $this->actingAs($this->lawyer)->post(route('requests.request-signature', $r))->assertRedirect();
        $this->assertSame(DataRequestStatus::AwaitingSignature, $r->fresh()->status);

        $this->sign($r, $this->lawyer)->assertForbidden();
        $this->assertSame(DataRequestStatus::AwaitingSignature, $r->fresh()->status);
        $this->assertSame(0, $this->gov->submitCalls);
    }

    public function test_revoked_licence_cannot_sign(): void
    {
        $this->basis();
        $r = $this->draft('salary');
        $this->advocate->advocateProfile->update(['license_status' => 'revoked']);

        $this->sign($r)->assertForbidden();

        $this->assertSame(DataRequestStatus::Draft, $r->fresh()->status);
        $this->assertSame(0, $this->gov->submitCalls);
    }

    public function test_signing_fails_closed_when_licence_registry_is_down(): void
    {
        $this->basis();
        $r = $this->draft('salary');
        $this->gov->registryDown = true;

        $this->sign($r)->assertSessionHasErrors('license');

        $this->assertSame(DataRequestStatus::Draft, $r->fresh()->status);
        $this->assertSame(0, $this->gov->submitCalls);
        $this->assertTrue(AuditLog::where('action', AuditAction::RequestBlocked)->where('meta->reason', 'license_registry_unavailable')->exists());
    }

    public function test_happy_path_submit_to_completed(): void
    {
        $this->basis();
        $r = $this->draft('salary');

        $this->sign($r)->assertSessionHasNoErrors()->assertRedirect(route('requests.show', $r));

        $r->refresh();
        $this->assertSame(DataRequestStatus::Processing, $r->status); // agency needs 3 s
        $this->assertSame('GOV-2026-000001', $r->gov_request_id);
        $this->assertNotNull($r->signature);
        $this->assertSame($this->advocate->id, $r->signed_by);
        $this->assertSame('2026-05', $this->gov->requests['GOV-2026-000001']['submission']->period['from']);
        $this->assertSame('ADV-00001', $this->gov->requests['GOV-2026-000001']['submission']->requesterLicense);
        $this->assertTrue(Transaction::where('data_request_id', $r->id)->where('type', 'charge')->exists());

        $this->travel(5)->seconds();
        FetchGovResultJob::dispatchSync($r->id);

        $r->refresh();
        $this->assertSame(DataRequestStatus::Completed, $r->status);
        $resp = $r->response;
        $this->assertSame(12_000_000, $resp->summary['total']);
        $this->assertSame(3, $resp->summary['months_count']);
        $this->assertSame(2, $resp->summary['employers_count']);
        $this->assertSame(4_000_000, $resp->summary['average_monthly']);
        $this->assertSame('2026-05', $resp->payload['data']['period']['from']);
        $this->assertTrue(now()->addDays(30)->isSameDay($resp->expires_at));
        $this->assertSame(1, $this->ai->analyzed);

        // payload is encrypted at rest
        $raw = \DB::table('request_responses')->where('id', $resp->id)->value('payload');
        $this->assertStringNotContainsString('Demo Qurilish', $raw);

        foreach ([AuditAction::RequestSigned, AuditAction::RequestSubmitted, AuditAction::ResponseReceived] as $action) {
            $this->assertTrue(AuditLog::where('action', $action)->where('subject_id', $r->id)->exists(), $action->value);
        }
    }

    public function test_insufficient_balance_blocks_submission(): void
    {
        $this->basis();
        $first = $this->draft('salary');
        $this->sign($first)->assertSessionHasNoErrors(); // uses the single included request

        $second = $this->draft('property', params: ['cadastral_number' => '10:01']);
        $this->sign($second)->assertSessionHasErrors('billing');
        $this->assertSame(DataRequestStatus::Draft, $second->fresh()->status);
    }

    public function test_rejection_refunds(): void
    {
        $this->basis();
        $this->gov->rejectWith = 'Shaxs topilmadi';
        $r = $this->draft('salary');
        $this->sign($r)->assertSessionHasNoErrors();

        $this->travel(5)->seconds();
        FetchGovResultJob::dispatchSync($r->id);

        $r->refresh();
        $this->assertSame(DataRequestStatus::Rejected, $r->status);
        $this->assertSame('Shaxs topilmadi', $r->rejection_reason);
        $this->assertTrue(Transaction::where('data_request_id', $r->id)->where('type', 'refund')->exists());
        $this->assertTrue(AuditLog::where('action', AuditAction::Refund)->where('subject_id', $r->id)->exists());
    }

    public function test_transport_failure_retries_then_fails_and_refunds(): void
    {
        $this->basis();
        $this->gov->failSubmit = true;
        $r = $this->draft('salary');
        $this->sign($r)->assertSessionHasNoErrors();

        $this->assertSame(3, $this->gov->submitCalls);
        $this->assertSame(DataRequestStatus::Failed, $r->fresh()->status);
        $this->assertTrue(Transaction::where('data_request_id', $r->id)->where('type', 'refund')->exists());
    }

    private function completed(): DataRequest
    {
        $this->basis();
        $r = $this->draft('salary');
        $this->sign($r)->assertSessionHasNoErrors();
        $this->travel(5)->seconds();
        FetchGovResultJob::dispatchSync($r->id);

        return $r->fresh();
    }

    public function test_lawyer_without_secrecy_declaration_gets_403_on_payload(): void
    {
        $r = $this->completed();

        $this->actingAs($this->lawyer)->getJson(route('requests.payload', $r))->assertForbidden();
        $this->actingAs($this->lawyer)->get(route('requests.pdf', $r))->assertForbidden();

        $this->actingAs($this->lawyer)->get(route('requests.show', $r))->assertOk()
            ->assertInertia(fn ($page) => $page->component('requests/show')
                ->where('permissions.needs_secrecy_declaration', true)
                ->where('response.summary', null));

        CaseMember::where('user_id', $this->lawyer->id)->update(['secrecy_declared_at' => now()]);
        $this->actingAs($this->lawyer)->getJson(route('requests.payload', $r))->assertOk()
            ->assertJsonPath('payload.data.total', 12_000_000);
    }

    public function test_lawyer_without_permission_and_admin_get_no_payload(): void
    {
        $r = $this->completed();
        CaseMember::where('user_id', $this->lawyer->id)->update(['can_view_responses' => false, 'secrecy_declared_at' => now()]);
        $this->actingAs($this->lawyer)->getJson(route('requests.payload', $r))->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->getJson(route('requests.payload', $r))->assertForbidden();
        $this->actingAs($admin)->get(route('requests.show', $r))->assertOk()
            ->assertInertia(fn ($page) => $page->where('response.summary', null)->where('permissions.is_admin', true));
    }

    public function test_every_view_creates_audit_row(): void
    {
        $r = $this->completed();
        $count = fn (AuditAction $a) => AuditLog::where('action', $a)->where('user_id', $this->advocate->id)->count();

        $this->actingAs($this->advocate)->get(route('requests.show', $r))->assertOk()
            ->assertInertia(fn ($page) => $page->where('response.summary.total', 12_000_000));
        $this->actingAs($this->advocate)->get(route('requests.show', $r))->assertOk();
        $this->assertSame(2, $count(AuditAction::ViewResponse));

        $this->actingAs($this->advocate)->getJson(route('requests.payload', $r))->assertOk();
        $this->actingAs($this->advocate)->getJson(route('requests.payload', $r))->assertOk();
        $this->assertSame(2, $count(AuditAction::ViewPayload));

        $this->actingAs($this->advocate)->get(route('requests.pdf', $r))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(1, $count(AuditAction::DownloadPdf));
        Storage::disk('local')->assertExists("responses/request-{$r->id}.pdf");
    }

    public function test_purge_command_removes_payload_after_retention(): void
    {
        $r = $this->completed();
        $this->actingAs($this->advocate)->get(route('requests.pdf', $r))->assertOk();

        $this->artisan('lexlink:purge-responses')->assertSuccessful();
        $this->assertNotNull(RequestResponse::first()->payload); // not yet expired

        $this->travel(31)->days();
        $this->artisan('lexlink:purge-responses')->assertSuccessful();

        $resp = RequestResponse::first();
        $this->assertNull($resp->payload);
        $this->assertNotNull($resp->purged_at);
        $this->assertSame(12_000_000, $resp->summary['total']); // summary kept
        Storage::disk('local')->assertMissing("responses/request-{$r->id}.pdf");
        $this->assertTrue(AuditLog::where('action', AuditAction::ResponsePurged)->exists());
        // the AI placeholder->real-value map is wiped together with the payload
        foreach (AiAnalysis::where('data_request_id', $r->id)->get() as $a) {
            $this->assertArrayNotHasKey('_pii', (array) $a->structured);
        }

        $this->actingAs($this->advocate)->getJson(route('requests.payload', $r))->assertStatus(410);
        $this->actingAs($this->advocate)->get(route('requests.show', $r))->assertOk()
            ->assertInertia(fn ($page) => $page->where('response.purged', true)->where('response.summary.total', 12_000_000));
    }

    public function test_create_page_and_live_check_and_draft_text(): void
    {
        $b = $this->basis();

        $this->actingAs($this->lawyer)->get(route('requests.create', ['case' => $this->case->id]))->assertOk()
            ->assertInertia(fn ($page) => $page->component('requests/create')->has('types', 8)->has('matrix.domains', 5)->has('subjects', 1)->where('can_sign', false));

        $this->actingAs($this->lawyer)->postJson(route('requests.check-basis'), [
            'case_id' => $this->case->id, 'type' => 'vehicle', 'subject_basis_id' => $b->id,
        ])->assertOk()->assertJsonPath('ok', false);

        $this->actingAs($this->lawyer)->postJson(route('requests.check-basis'), [
            'case_id' => $this->case->id, 'type' => 'property', 'subject_basis_id' => $b->id,
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('basis.number', '2-1001-2611/0457');

        $this->actingAs($this->lawyer)->postJson(route('requests.draft-text'), [
            'case_id' => $this->case->id, 'type' => 'property', 'subject_basis_id' => $b->id,
        ])->assertOk()->assertJsonPath('source', 'ai');

        $this->app->forgetInstance(AiService::class);
        $this->app->offsetUnset(AiService::class);
        $this->actingAs($this->lawyer)->postJson(route('requests.draft-text'), [
            'case_id' => $this->case->id, 'type' => 'salary', 'subject_basis_id' => $b->id, 'params' => ['period_from' => '2026-01', 'period_to' => '2026-06'],
        ])->assertOk()->assertJsonPath('source', 'template');

        $this->actingAs($this->lawyer)->post(route('requests.store'), [
            'case_id' => $this->case->id, 'type' => 'property', 'subject_basis_id' => $b->id,
            'purpose' => 'Mulkni aniqlash', 'request_text' => 'Matn', 'intent' => 'signature',
        ])->assertRedirect();
        $created = DataRequest::latest('id')->first();
        $this->assertSame(DataRequestStatus::AwaitingSignature, $created->status);
        $this->assertSame(self::SUBJECT, $created->subject_pinfl);
        $this->assertSame($b->id, $created->legal_basis_id);

        $this->actingAs($this->lawyer)->get(route('requests.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('requests/index')->has('requests.data', 1));
    }
}

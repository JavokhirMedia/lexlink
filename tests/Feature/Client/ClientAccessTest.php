<?php

namespace Tests\Feature\Client;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\UserRole;
use App\Models\AiAnalysis;
use App\Models\AuditLog;
use App\Models\CaseClientLink;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\Mock\MockAdvocateOrder;
use App\Models\RequestResponse;
use App\Models\User;
use App\Support\Pinfl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Orders\OrdersTestHelpers;
use Tests\TestCase;

class ClientAccessTest extends TestCase
{
    use OrdersTestHelpers;
    use RefreshDatabase;

    private User $adv;

    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootOrders();
        $this->adv = $this->advocateUser();
        $this->case = $this->caseOf($this->adv, '2-1001-2610/0398');
        $this->registryOrder('ORD-2026-000457');
    }

    private function verifiedOrderBasis(): LegalBasis
    {
        $this->addOrderBasis($this->adv, $this->case, 'ORD-2026-000457')->assertSessionHasNoErrors();

        return LegalBasis::where('number', 'ORD-2026-000457')->firstOrFail();
    }

    private function link(): CaseClientLink
    {
        $this->verifiedOrderBasis();
        $this->actingAs($this->adv)->post(route('cases.client-links.store', $this->case), ['order_number' => 'ORD-2026-000457'])
            ->assertSessionHasNoErrors();

        return CaseClientLink::firstOrFail();
    }

    private function oneIdUser(string $pinfl, ?UserRole $role = null): User
    {
        return User::factory()->create(['pinfl' => $pinfl, 'role' => $role, 'name' => 'Karimova Nilufar Shavkatovna']);
    }

    public function test_link_requires_a_verified_order_with_known_client(): void
    {
        // nothing verified and the number is not in the registry
        $this->actingAs($this->adv)->post(route('cases.client-links.store', $this->case), ['order_number' => 'ORD-2026-000999'])
            ->assertSessionHasErrors('order_number');
        // another advocate's registry order is refused too
        $this->registryOrder('ORD-2026-000398', 'AL-00045');
        $this->actingAs($this->adv)->post(route('cases.client-links.store', $this->case), ['order_number' => 'ORD-2026-000398'])
            ->assertSessionHasErrors('order_number');
        $this->assertSame(0, CaseClientLink::count());

        $link = $this->link();
        $this->assertSame(Pinfl::hash(self::CLIENT_PINFL), $link->client_pinfl_hash);
        $this->assertSame('Karimova Nilufar Shavkatovna', $link->client_name);
        $this->assertTrue(AuditLog::where('action', AuditAction::ClientLinkCreated)->where('case_id', $this->case->id)->exists());

        // lawyers cannot create client links
        $lawyer = User::factory()->lawyer()->create();
        $this->case->memberships()->create(['user_id' => $lawyer->id, 'role' => 'lawyer']);
        $this->actingAs($lawyer)->post(route('cases.client-links.store', $this->case), ['order_number' => 'ORD-2026-000457'])->assertForbidden();
    }

    public function test_guest_is_sent_to_oneid_login_and_back(): void
    {
        $link = $this->link();
        auth()->logout();

        $this->get(route('client.access', $link->token))->assertRedirect(route('login'));
        $this->assertSame(route('client.access', $link->token), session('url.intended'));
    }

    public function test_new_oneid_user_with_matching_pinfl_becomes_client_and_is_granted(): void
    {
        $link = $this->link();
        $client = $this->oneIdUser(self::CLIENT_PINFL);

        $this->actingAs($client)->get(route('client.access', $link->token))
            ->assertRedirect(route('client.cases.show', $this->case->id));

        $this->assertSame(UserRole::Client, $client->fresh()->role);
        $this->assertSame($client->id, $link->fresh()->client_user_id);
        $this->assertNotNull($link->fresh()->granted_at);
        $this->assertTrue(AuditLog::where('action', AuditAction::ClientAccessGranted)->where('user_id', $client->id)->where('case_id', $this->case->id)->exists());

        $this->actingAs($client->fresh())->get(route('client.index'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('client/index')->has('cases', 1)->where('cases.0.order_number', 'ORD-2026-000457'));
        $this->actingAs($client->fresh())->get(route('dashboard'))->assertRedirect(route('client.index'));
    }

    public function test_mismatching_pinfl_is_denied_with_403_and_audited(): void
    {
        $link = $this->link();
        $stranger = $this->oneIdUser('30202780262011');

        $this->actingAs($stranger)->get(route('client.access', $link->token))
            ->assertForbidden()
            ->assertInertia(fn (Assert $p) => $p->component('client/denied')->where('reason', 'pinfl_mismatch'));

        $this->assertNull($stranger->fresh()->role);
        $this->assertNull($link->fresh()->client_user_id);
        $this->assertTrue(AuditLog::where('action', AuditAction::ClientAccessDenied)->where('user_id', $stranger->id)->exists());
        $this->actingAs($stranger->fresh())->get(route('client.cases.show', $this->case->id))->assertRedirect(route('onboarding.show'));
    }

    public function test_advocate_role_revoked_link_and_cancelled_order_are_denied(): void
    {
        $link = $this->link();

        $otherAdv = User::factory()->advocate()->create(['pinfl' => self::CLIENT_PINFL]);
        $this->actingAs($otherAdv)->get(route('client.access', $link->token))->assertForbidden()
            ->assertInertia(fn (Assert $p) => $p->where('reason', 'role'));
        $otherAdv->delete();

        $client = $this->oneIdUser(self::CLIENT_PINFL);
        MockAdvocateOrder::where('order_number', 'ORD-2026-000457')->update(['status' => 'cancelled']);
        $this->actingAs($client)->get(route('client.access', $link->token))->assertForbidden()
            ->assertInertia(fn (Assert $p) => $p->where('reason', 'order_inactive'));
        MockAdvocateOrder::where('order_number', 'ORD-2026-000457')->update(['status' => 'active']);

        $this->actingAs($this->adv)->delete(route('cases.client-links.destroy', [$this->case, $link]));
        $this->assertNotNull($link->fresh()->revoked_at);
        $this->assertTrue(AuditLog::where('action', AuditAction::ClientLinkRevoked)->exists());
        $this->actingAs($client)->get(route('client.access', $link->token))->assertForbidden()
            ->assertInertia(fn (Assert $p) => $p->where('reason', 'link_invalid'));
    }

    public function test_onboarding_never_offers_the_client_role(): void
    {
        $fresh = $this->oneIdUser('30202780262011');
        $this->actingAs($fresh)->post(route('onboarding.role.store'), ['role' => 'client'])->assertSessionHasErrors('role');
        $this->assertNull($fresh->fresh()->role);
    }

    public function test_client_sees_summary_only_never_payload_and_every_view_is_audited(): void
    {
        $link = $this->link();
        $client = $this->oneIdUser(self::CLIENT_PINFL);
        $this->actingAs($client)->get(route('client.access', $link->token));
        $client->refresh();

        $basis = LegalBasis::firstOrFail();
        $completed = DataRequest::create([
            'case_id' => $this->case->id, 'legal_basis_id' => $basis->id, 'type' => DataRequestType::Vehicle,
            'subject_pinfl' => self::SUBJECT_PINFL, 'subject_name' => 'Karimov Aziz Botirovich',
            'purpose' => 'Mol-mulk', 'request_text' => "So'rov", 'status' => DataRequestStatus::Completed,
            'created_by' => $this->adv->id, 'signed_by' => $this->adv->id, 'signed_at' => now()->subDay(),
            'submitted_at' => now()->subDay(), 'due_at' => now()->addDays(2), 'deadline_basis' => 'proceedings', 'completed_at' => now(),
            'gov_request_id' => 'GOV-2026-000777',
        ]);
        RequestResponse::create([
            'data_request_id' => $completed->id,
            'summary' => ['type' => 'vehicle', 'vehicles_count' => 1, 'pledged_count' => 1, 'newest_year' => 2019],
            'payload' => ['data' => ['vehicles' => [['plate' => '01 A 777 AA', 'vin' => 'XWB-SECRET-VIN', 'owner' => 'Karimov Aziz Botirovich']]], 'subject' => ['pinfl' => self::SUBJECT_PINFL]],
            'pdf_path' => 'responses/secret.pdf',
            'received_at' => now(),
        ]);
        AiAnalysis::create([
            'data_request_id' => $completed->id, 'case_id' => $this->case->id, 'created_by' => $this->adv->id,
            'kind' => 'summary', 'status' => 'completed', 'output' => '[SHAXS_1] nomida 1 ta garovdagi transport vositasi bor.',
            'structured' => ['_pii' => ['[SHAXS_1]' => 'Karimov Aziz Botirovich']],
        ]);
        DataRequest::create([
            'case_id' => $this->case->id, 'type' => DataRequestType::Property, 'subject_pinfl' => self::SUBJECT_PINFL,
            'status' => DataRequestStatus::Draft, 'created_by' => $this->adv->id, 'request_text' => 'draft',
        ]);

        $res = $this->actingAs($client)->get(route('client.cases.show', $this->case->id))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('client/case')
                ->where('case.order_number', 'ORD-2026-000457')
                ->where('case.advocate.license_number', 'AL-00123')
                ->has('requests', 1) // drafts are hidden
                ->where('requests.0.status', 'completed')
                ->where('requests.0.answered_on_time', true)
                ->where('requests.0.ai_summary', '[SHAXS_1] nomida 1 ta garovdagi transport vositasi bor.')
                ->where('note', fn ($n) => str_contains($n, "To'liq javob advokatda saqlanadi"))
                ->missing('requests.0.payload')
                ->missing('requests.0.pdf_url')
                ->missing('requests.0.subject_pinfl_masked')
                ->has('requests.0.summary_items', 3));

        $props = json_encode($res->viewData('page')['props']);
        foreach (['01 A 777 AA', 'XWB-SECRET-VIN', 'secret.pdf', self::SUBJECT_PINFL, self::CLIENT_PINFL, 'Karimov Aziz', 'payload', 'GOV-2026-000777'] as $needle) {
            $this->assertStringNotContainsString($needle, $props, "client props leaked: {$needle}");
        }

        $this->assertSame(1, AuditLog::where('action', AuditAction::ClientViewed)->where('user_id', $client->id)->where('case_id', $this->case->id)->count());

        // The client never reaches the advocate's pages, payload, PDF or request creation.
        $this->actingAs($client)->get(route('requests.payload', $completed))->assertForbidden();
        $this->actingAs($client)->get(route('requests.pdf', $completed))->assertForbidden();
        $this->actingAs($client)->get(route('requests.show', $completed))->assertForbidden();
        $this->actingAs($client)->get(route('cases.show', $this->case))->assertForbidden();
        $this->actingAs($client)->post(route('requests.store'), ['case_id' => $this->case->id, 'type' => 'vehicle'])->assertForbidden();
        $this->assertSame(2, DataRequest::count());
    }

    public function test_client_without_granted_link_cannot_open_case(): void
    {
        $this->link();
        $client = $this->oneIdUser(self::CLIENT_PINFL, UserRole::Client);

        $this->actingAs($client)->get(route('client.cases.show', $this->case->id))->assertForbidden()
            ->assertInertia(fn (Assert $p) => $p->component('client/denied'));
    }
}

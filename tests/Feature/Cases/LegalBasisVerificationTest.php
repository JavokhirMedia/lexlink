<?php

namespace Tests\Feature\Cases;

use App\Enums\AuditAction;
use App\Enums\LegalBasisType;
use App\Models\AuditLog;
use App\Models\LegalBasis;
use App\Services\LegalBasisVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalBasisVerificationTest extends TestCase
{
    use CasesTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeGov();
        $this->withoutVite();
    }

    public function test_court_decision_is_verified_from_registry(): void
    {
        Storage::fake('local');
        $this->courtDecisions['SA-2026-001'] = $this->decision('SA-2026-001');
        $adv = $this->advocate();
        $case = $this->caseFor($adv);

        $this->actingAs($adv)->post(route('cases.bases.store', $case), [
            'type' => 'court_decision',
            'number' => 'SA-2026-001',
            'file' => UploadedFile::fake()->create('ajrim.pdf', 50, 'application/pdf'),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $b = LegalBasis::firstOrFail();
        $this->assertSame('verified', $b->verification_status);
        $this->assertSame('31234567890112', $b->subject_pinfl);
        $this->assertSame('3***********12', $b->subject_pinfl_masked);
        $this->assertSame('Karimov Aziz', $b->subject_name);
        $this->assertSame(['salary', 'property'], $b->allowed_types);
        $this->assertNotNull($b->court_name);
        $this->assertNotNull($b->verified_at);
        $this->assertNotNull($b->expires_at);
        $this->assertStringNotContainsString('31234567890112', json_encode($b->verification_payload));
        Storage::disk('local')->assertExists($b->file_path);

        $this->assertTrue(AuditLog::where('action', AuditAction::LegalBasisAdded)->where('case_id', $case->id)->exists());
        $this->assertTrue(AuditLog::where('action', AuditAction::LegalBasisVerified)->where('case_id', $case->id)->exists());

        $coverage = app(LegalBasisVerifier::class)->coverage($case);
        $this->assertCount(1, $coverage['salary']);
        $this->assertSame($b->id, $coverage['salary'][0]['basis_id']);
        $this->assertCount(1, $coverage['property']);
        $this->assertCount(0, $coverage['vehicle']);
    }

    public function test_unknown_court_decision_is_not_found(): void
    {
        $adv = $this->advocate();
        $case = $this->caseFor($adv);

        $this->actingAs($adv)->post(route('cases.bases.store', $case), ['type' => 'court_decision', 'number' => 'NOPE-1']);

        $b = LegalBasis::firstOrFail();
        $this->assertSame('not_found', $b->verification_status);
        $this->assertSame([], $app = app(LegalBasisVerifier::class)->coverage($case)['salary']);
    }

    public function test_expired_court_decision(): void
    {
        $this->courtDecisions['OLD-1'] = $this->decision('OLD-1', ['expires_at' => now()->subDay()->toDateString()]);
        $adv = $this->advocate();
        $case = $this->caseFor($adv);

        $this->actingAs($adv)->post(route('cases.bases.store', $case), ['type' => 'court_decision', 'number' => 'OLD-1']);

        $b = LegalBasis::firstOrFail();
        $this->assertSame('expired', $b->verification_status);
        $this->assertSame([], app(LegalBasisVerifier::class)->coverage($case)['salary']);
    }

    public function test_reverify_picks_up_registry_changes(): void
    {
        $adv = $this->advocate();
        $case = $this->caseFor($adv);
        $this->actingAs($adv)->post(route('cases.bases.store', $case), ['type' => 'court_decision', 'number' => 'LATE-1']);
        $b = LegalBasis::firstOrFail();
        $this->assertSame('not_found', $b->verification_status);

        $this->courtDecisions['LATE-1'] = $this->decision('LATE-1');
        $this->actingAs($adv)->post(route('cases.bases.verify', [$case, $b]))->assertRedirect();

        $this->assertSame('verified', $b->fresh()->verification_status);
    }

    public function test_advocate_order_cannot_include_salary(): void
    {
        $adv = $this->advocate();
        $case = $this->caseFor($adv);
        $this->order('ORD-8', $adv);

        $this->actingAs($adv)->post(route('cases.bases.store', $case), [
            'type' => 'advocate_order',
            'number' => 'ORD-7',
            'purpose' => 'Aliment ishi bo\'yicha mol-mulkni aniqlash',
            'subject_pinfl' => '31234567890112',
            'allowed_types' => ['salary', 'property'],
            'expires_at' => now()->addMonth()->toDateString(),
        ])->assertSessionHasErrors('allowed_types.0');
        $this->assertSame(0, LegalBasis::count());

        // Even if salary slips into the model, the verifier strips it.
        $b = LegalBasis::create([
            'case_id' => $case->id, 'created_by' => $adv->id, 'type' => LegalBasisType::AdvocateOrder,
            'number' => 'ORD-8', 'subject_pinfl' => '31234567890112', 'allowed_types' => ['salary', 'vehicle'],
            'expires_at' => now()->addMonth()->toDateString(),
        ]);
        $this->actingAs($adv);
        app(LegalBasisVerifier::class)->verify($b);
        $this->assertSame('verified', $b->verification_status);
        $this->assertSame(['vehicle'], $b->fresh()->allowed_types);
        $this->assertSame([], app(LegalBasisVerifier::class)->coverage($case)['salary']);
    }

    public function test_advocate_order_verified_for_licensed_advocate(): void
    {
        $adv = $this->advocate();
        $case = $this->caseFor($adv);
        $this->order('ORD-9', $adv);

        $this->actingAs($adv)->post(route('cases.bases.store', $case), [
            'type' => 'advocate_order',
            'number' => 'ORD-9',
            'purpose' => 'Aliment ishi bo\'yicha mol-mulkni aniqlash',
            'subject_pinfl' => '31234567890112',
            'subject_name' => 'Karimov Aziz',
            'allowed_types' => ['property', 'vehicle'],
            'expires_at' => now()->addMonth()->toDateString(),
        ])->assertSessionHasNoErrors();

        $b = LegalBasis::firstOrFail();
        $this->assertSame('verified', $b->verification_status);
        $this->assertSame(['property', 'vehicle'], $b->allowed_types);
        $this->assertCount(1, app(LegalBasisVerifier::class)->coverage($case)['vehicle']);
    }

    public function test_advocate_order_rejected_when_registry_says_licence_revoked(): void
    {
        $this->licenseStatus = 'revoked';
        $adv = $this->advocate();
        $case = $this->caseFor($adv);
        $b = LegalBasis::create([
            'case_id' => $case->id, 'created_by' => $adv->id, 'type' => LegalBasisType::AdvocateOrder,
            'number' => 'ORD-10', 'subject_pinfl' => '31234567890112', 'allowed_types' => ['vehicle'],
            'expires_at' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($adv);
        app(LegalBasisVerifier::class)->verify($b);

        $this->assertSame('mismatch', $b->verification_status);
    }

    public function test_lawyer_cannot_add_basis(): void
    {
        $adv = $this->advocate();
        $lawyer = $this->lawyer();
        $case = $this->caseFor($adv, $lawyer);

        $this->actingAs($lawyer)->post(route('cases.bases.store', $case), ['type' => 'court_decision', 'number' => 'X'])->assertForbidden();
    }
}

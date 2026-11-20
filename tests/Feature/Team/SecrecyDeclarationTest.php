<?php

namespace Tests\Feature\Team;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecrecyDeclarationTest extends TestCase
{
    use RefreshDatabase;

    private LegalCase $case;

    private User $lawyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $advocate = User::factory()->advocate()->create();
        $this->lawyer = User::factory()->lawyer()->create();
        $this->case = LegalCase::create(['owner_id' => $advocate->id, 'title' => 'Aliment', 'client_name' => 'Mijoz']);
        CaseMember::create(['case_id' => $this->case->id, 'user_id' => $this->lawyer->id, 'role' => 'lawyer']);
    }

    public function test_member_lawyer_can_declare_secrecy(): void
    {
        $this->actingAs($this->lawyer)
            ->from(route('dashboard'))
            ->post(route('cases.secrecy-declaration.store', $this->case), ['accepted' => true])
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($this->case->membershipOf($this->lawyer)->secrecy_declared_at);

        $log = AuditLog::where('action', AuditAction::SecrecyDeclared)->firstOrFail();
        $this->assertSame($this->case->id, $log->case_id);
        $this->assertSame($this->lawyer->id, $log->user_id);
    }

    public function test_declaring_twice_keeps_first_timestamp_and_single_audit(): void
    {
        $this->actingAs($this->lawyer)->post(route('cases.secrecy-declaration.store', $this->case), ['accepted' => true]);
        $first = $this->case->membershipOf($this->lawyer)->secrecy_declared_at;
        $this->travel(5)->minutes();
        $this->actingAs($this->lawyer)->post(route('cases.secrecy-declaration.store', $this->case), ['accepted' => true]);

        $this->assertEquals($first, $this->case->membershipOf($this->lawyer)->secrecy_declared_at);
        $this->assertSame(1, AuditLog::where('action', AuditAction::SecrecyDeclared)->count());
    }

    public function test_acceptance_flag_is_required(): void
    {
        $this->actingAs($this->lawyer)
            ->post(route('cases.secrecy-declaration.store', $this->case), [])
            ->assertSessionHasErrors('accepted');

        $this->assertNull($this->case->membershipOf($this->lawyer)->secrecy_declared_at);
    }

    public function test_non_member_is_forbidden(): void
    {
        $this->actingAs(User::factory()->lawyer()->create())
            ->post(route('cases.secrecy-declaration.store', $this->case), ['accepted' => true])
            ->assertForbidden();
    }

    public function test_json_response_for_api_clients(): void
    {
        $this->actingAs($this->lawyer)
            ->postJson(route('cases.secrecy-declaration.store', $this->case), ['accepted' => true])
            ->assertOk()
            ->assertJsonStructure(['secrecy_declared_at']);
    }
}

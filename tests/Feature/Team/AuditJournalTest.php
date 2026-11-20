<?php

namespace Tests\Feature\Team;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditJournalTest extends TestCase
{
    use RefreshDatabase;

    private User $advocate;

    private User $lawyer;

    private User $stranger;

    private LegalCase $case;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->advocate = User::factory()->advocate()->create();
        $this->lawyer = User::factory()->lawyer()->create();
        $this->stranger = User::factory()->advocate()->create();
        $this->case = LegalCase::create(['owner_id' => $this->advocate->id, 'title' => 'Aliment', 'client_name' => 'Mijoz']);

        $audit = app(AuditLogger::class);
        $audit->log(AuditAction::Login, user: $this->advocate);                                  // advocate own
        $audit->log(AuditAction::ViewResponse, caseId: $this->case->id, user: $this->lawyer);   // lawyer on advocate's case
        $audit->log(AuditAction::Login, user: $this->lawyer);                                    // lawyer own
        $audit->log(AuditAction::Login, user: $this->stranger);                                  // unrelated
    }

    private function ids(User $user, array $query = []): array
    {
        $ids = [];

        $this->actingAs($user)->get(route('audit.index', $query))
            ->assertOk()
            ->assertInertia(function ($page) use (&$ids) {
                $page->component('audit/index');
                $ids = collect($page->toArray()['props']['entries']['data'])->pluck('user.id')->all();
            });

        return $ids;
    }

    public function test_advocate_sees_own_actions_and_own_cases(): void
    {
        $ids = $this->ids($this->advocate);

        $this->assertCount(2, $ids);
        $this->assertContains($this->advocate->id, $ids);
        $this->assertContains($this->lawyer->id, $ids);
        $this->assertNotContains($this->stranger->id, $ids);
    }

    public function test_lawyer_sees_only_own_actions(): void
    {
        $ids = $this->ids($this->lawyer);

        $this->assertCount(2, $ids);
        $this->assertSame([$this->lawyer->id], array_values(array_unique($ids)));
    }

    public function test_admin_sees_everything(): void
    {
        $this->assertCount(4, $this->ids(User::factory()->admin()->create()));
    }

    public function test_filters_by_action_case_and_date(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertCount(3, $this->ids($admin, ['action' => 'login']));
        $this->assertCount(1, $this->ids($admin, ['case_id' => $this->case->id]));
        $this->assertCount(0, $this->ids($admin, ['date_from' => now()->addDay()->toDateString()]));
        $this->assertCount(4, $this->ids($admin, ['date_to' => now()->toDateString()]));
    }

    public function test_entries_have_uzbek_labels_and_pagination(): void
    {
        foreach (range(1, 30) as $i) {
            app(AuditLogger::class)->log(AuditAction::Logout, user: $this->lawyer);
        }

        $this->actingAs($this->lawyer)->get(route('audit.index'))
            ->assertInertia(fn ($p) => $p
                ->has('entries.data', 25)
                ->where('entries.last_page', 2)
                ->where('entries.data.0.action_label', AuditAction::Logout->label())
                ->has('actions')
                ->where('scope', 'own'));
    }

    public function test_invalid_action_filter_is_rejected(): void
    {
        $this->actingAs($this->lawyer)->get(route('audit.index', ['action' => 'nope']))->assertSessionHasErrors('action');
    }

    public function test_journal_is_read_only(): void
    {
        $this->expectException(\LogicException::class);
        AuditLog::first()->update(['action' => AuditAction::Logout]);
    }
}

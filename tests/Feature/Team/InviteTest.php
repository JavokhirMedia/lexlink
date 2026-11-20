<?php

namespace Tests\Feature\Team;

use App\Enums\AuditAction;
use App\Enums\UserRole;
use App\Models\AdvocateLawyerLink;
use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InviteTest extends TestCase
{
    use RefreshDatabase;

    private User $advocate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->advocate = User::factory()->advocate()->create();
    }

    private function invite(string $contact = 'yurist@example.uz'): AdvocateLawyerLink
    {
        $this->actingAs($this->advocate)
            ->post(route('team.invites.store'), ['contact' => $contact])
            ->assertRedirect(route('team.index'))
            ->assertSessionHasNoErrors();

        return AdvocateLawyerLink::latest('id')->firstOrFail();
    }

    public function test_advocate_sees_team_page(): void
    {
        $this->actingAs($this->advocate)->get(route('team.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('team/index')->where('mode', 'advocate'));
    }

    public function test_advocate_can_invite_by_email_or_phone(): void
    {
        $link = $this->invite('Yurist@Example.uz');
        $this->assertSame('yurist@example.uz', $link->invite_contact);
        $this->assertSame('pending', $link->status);
        $this->assertSame(48, strlen($link->token));
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LawyerInvited->value, 'user_id' => $this->advocate->id]);

        $phone = $this->invite('+998 90 123-45-67');
        $this->assertSame('+998901234567', $phone->invite_contact);

        $this->actingAs($this->advocate)->get(route('team.index'))
            ->assertInertia(fn ($p) => $p->has('links', 2)
                ->where('links.0.invite_url', route('invites.show', $phone->token)));
    }

    public function test_invalid_contact_is_rejected(): void
    {
        $this->actingAs($this->advocate)
            ->post(route('team.invites.store'), ['contact' => 'hello'])
            ->assertSessionHasErrors('contact');
    }

    public function test_lawyer_cannot_invite(): void
    {
        $this->actingAs(User::factory()->lawyer()->create())
            ->post(route('team.invites.store'), ['contact' => 'a@b.uz'])
            ->assertForbidden();
    }

    public function test_guest_opening_invite_is_sent_to_login_and_back(): void
    {
        $link = $this->invite();
        auth()->logout();

        $this->get(route('invites.show', $link->token))->assertRedirect(route('login'));
        $this->assertSame(route('invites.show', $link->token), session('url.intended'));
    }

    public function test_lawyer_accepts_invite(): void
    {
        $link = $this->invite();
        $lawyer = User::factory()->lawyer()->create();

        $this->actingAs($lawyer)->get(route('invites.show', $link->token))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('invites/accept')->where('can_accept', true));

        $this->actingAs($lawyer)->post(route('invites.accept', $link->token))
            ->assertRedirect(route('team.index'));

        $link->refresh();
        $this->assertSame('accepted', $link->status);
        $this->assertSame($lawyer->id, $link->lawyer_id);
        $this->assertNotNull($link->accepted_at);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::LawyerJoined->value, 'user_id' => $lawyer->id]);

        // Lawyer sees the advocate in their team list.
        $this->actingAs($lawyer)->get(route('team.index'))
            ->assertInertia(fn ($p) => $p->where('mode', 'lawyer')->where('links.0.person.id', $this->advocate->id));
    }

    public function test_fresh_user_accepting_invite_becomes_lawyer(): void
    {
        $link = $this->invite();
        $fresh = User::factory()->unonboarded()->create();

        $this->actingAs($fresh)->post(route('invites.accept', $link->token))->assertRedirect(route('team.index'));

        $this->assertSame(UserRole::Lawyer, $fresh->fresh()->role);
        $this->assertNotNull($fresh->fresh()->wallet);
    }

    public function test_lawyer_can_work_with_several_advocates(): void
    {
        $lawyer = User::factory()->lawyer()->create();
        $first = $this->invite('a@x.uz');
        $this->actingAs($lawyer)->post(route('invites.accept', $first->token))->assertSessionHasNoErrors();

        $other = User::factory()->advocate()->create();
        $this->actingAs($other)->post(route('team.invites.store'), ['contact' => 'a@x.uz'])->assertSessionHasNoErrors();
        $this->actingAs($lawyer)->post(route('invites.accept', AdvocateLawyerLink::latest('id')->first()->token))->assertSessionHasNoErrors();

        $this->assertSame(2, $lawyer->advocateLinks()->where('status', 'accepted')->count());
    }

    public function test_advocates_cannot_accept_and_invites_are_single_use(): void
    {
        $link = $this->invite();

        $this->actingAs(User::factory()->advocate()->create())
            ->post(route('invites.accept', $link->token))
            ->assertSessionHasErrors('invite');

        $first = User::factory()->lawyer()->create();
        $this->actingAs($first)->post(route('invites.accept', $link->token));

        $this->actingAs(User::factory()->lawyer()->create())
            ->post(route('invites.accept', $link->token))
            ->assertSessionHasErrors('invite');

        $this->assertSame($first->id, $link->fresh()->lawyer_id);
    }

    public function test_advocate_can_revoke_lawyer(): void
    {
        $link = $this->invite();
        $lawyer = User::factory()->lawyer()->create();
        $this->actingAs($lawyer)->post(route('invites.accept', $link->token));

        $case = LegalCase::create(['owner_id' => $this->advocate->id, 'title' => 'Aliment', 'client_name' => 'Mijoz']);
        CaseMember::create(['case_id' => $case->id, 'user_id' => $lawyer->id, 'role' => 'lawyer', 'can_view_responses' => true]);

        $this->actingAs($this->advocate)->delete(route('team.links.destroy', $link))
            ->assertRedirect(route('team.index'));

        $this->assertSame('revoked', $link->fresh()->status);
        $this->assertDatabaseMissing('case_members', ['case_id' => $case->id, 'user_id' => $lawyer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => AuditAction::PermissionChanged->value, 'subject_id' => $link->id]);

        // Revoked invite can no longer be used.
        $this->actingAs(User::factory()->lawyer()->create())
            ->post(route('invites.accept', $link->token))
            ->assertSessionHasErrors('invite');
    }

    public function test_advocate_cannot_revoke_foreign_link(): void
    {
        $link = $this->invite();

        $this->actingAs(User::factory()->advocate()->create())
            ->delete(route('team.links.destroy', $link))
            ->assertForbidden();

        $this->assertSame('pending', $link->fresh()->status);
    }
}

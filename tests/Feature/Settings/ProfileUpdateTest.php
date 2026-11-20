<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_profile_page_shows_oneid_identity_with_masked_pinfl(): void
    {
        $user = User::factory()->create(['pinfl' => '31204851234567']);

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('settings/profile')
                ->where('profile.name', $user->name)
                ->where('profile.pinfl_masked', '3***********67'));
    }

    public function test_only_email_can_be_updated(): void
    {
        $user = User::factory()->create(['name' => 'OneID Name']);

        $this->actingAs($user)
            ->patch(route('profile.update'), ['name' => 'Hacked', 'email' => 'NEW@Example.uz'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('OneID Name', $user->name);
        $this->assertSame('new@example.uz', $user->email);
    }

    public function test_email_can_be_cleared(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), ['email' => ''])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->email);
    }

    public function test_email_must_be_unique(): void
    {
        User::factory()->create(['email' => 'taken@example.uz']);

        $this->actingAs(User::factory()->create())
            ->patch(route('profile.update'), ['email' => 'taken@example.uz'])
            ->assertSessionHasErrors('email');
    }

    public function test_appearance_page_is_available(): void
    {
        $this->actingAs(User::factory()->create())->get(route('appearance.edit'))->assertOk();
    }
}

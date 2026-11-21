<?php

namespace Tests\Feature\LegalMap;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalMapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /** @return array<string, string> type value => access */
    private function accessByType(array $domains): array
    {
        $map = [];
        foreach ($domains as $d) {
            foreach ($d['types'] as $t) {
                $map[$t['value']] = $t['access'];
            }
        }

        return $map;
    }

    public function test_guest_can_open_legal_map_with_full_matrix(): void
    {
        $this->get(route('legal-map'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal-map')
                ->has('matrix.domains', 5)
                ->has('matrix.out_of_scope')
                ->where('matrix.deadlines.default_days', 15)
                ->where('matrix.deadlines.proceedings_work_days', 3)
                ->has('matrix.disclaimer')
                ->has('secrecy', 8)
                ->where('can_create_request', false)
                ->where('matrix.domains', function ($domains) {
                    $access = $this->accessByType(collect($domains)->toArray());

                    $this->assertCount(8, $access);
                    foreach (['salary', 'bank_accounts', 'border_crossing'] as $courtOnly) {
                        $this->assertSame('court', $access[$courtOnly], $courtOnly);
                    }
                    foreach (['court_case', 'tax_info', 'property', 'vehicle', 'business_shares'] as $orderOk) {
                        $this->assertSame('order', $access[$orderOk], $orderOk);
                    }

                    return true;
                }));
    }

    public function test_court_only_types_accept_only_court_decision(): void
    {
        $this->get(route('legal-map'))->assertInertia(fn (Assert $page) => $page
            ->where('matrix.domains', function ($domains) {
                foreach (collect($domains)->toArray() as $d) {
                    foreach ($d['types'] as $t) {
                        $bases = array_column($t['accepted_bases'], 'value');
                        $t['access'] === 'court'
                            ? $this->assertSame(['court_decision'], $bases, $t['value'])
                            : $this->assertContains('advocate_order', $bases, $t['value']);
                    }
                }

                return true;
            }));
    }

    public function test_advocate_can_open_legal_map_and_create_requests(): void
    {
        $advocate = User::factory()->create(['role' => UserRole::Advocate]);

        $this->actingAs($advocate)->get(route('legal-map'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('legal-map')
                ->has('matrix.domains', 5)
                ->where('can_create_request', true));
    }

    public function test_admin_sees_map_without_request_links(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)->get(route('legal-map'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('can_create_request', false));
    }

    public function test_welcome_page_receives_matrix(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('welcome')
                ->has('matrix.domains', 5));
    }
}

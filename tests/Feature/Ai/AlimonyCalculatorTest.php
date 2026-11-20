<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\AlimonyCalculator;
use Tests\TestCase;

class AlimonyCalculatorTest extends TestCase
{
    public function test_average_and_shares_follow_family_code_article_99(): void
    {
        $calc = (new AlimonyCalculator)->calculate([
            ['period' => '2026-01', 'amount' => 3_000_000],
            ['period' => '2026-02', 'amount' => 4_000_000],
            ['period' => '2026-03', 'amount' => 4_000_000],
            ['period' => '2026-03', 'amount' => 1_000_000], // second employer, same month
        ], null, 1_000_000);

        $this->assertSame(3, $calc['months_count']);
        $this->assertSame(12_000_000, $calc['total_income']);
        $this->assertSame(4_000_000, $calc['average_monthly']);
        $this->assertSame(['1/4', '1/3', '1/2'], array_column($calc['options'], 'share'));
        $this->assertSame([1_000_000, 1_333_333, 2_000_000], array_column($calc['options'], 'amount'));
        $this->assertNull($calc['selected']);
        $this->assertSame(['from' => '2026-01', 'to' => '2026-03'], $calc['period']);
    }

    public function test_selected_children_and_minimum_floor(): void
    {
        $c = new AlimonyCalculator;
        $this->assertSame('1/3', $c->calculate([['period' => '2026-01', 'amount' => 6_000_000]], 2, 1_000_000)['selected']['share']);
        $this->assertSame('1/2', $c->calculate([['period' => '2026-01', 'amount' => 6_000_000]], 5, 1_000_000)['selected']['share']);

        // 26.5% of the minimum wage per child: 1 000 000 * 0.265 = 265 000
        $low = $c->calculate([['period' => '2026-01', 'amount' => 800_000]], 1, 1_000_000);
        $this->assertSame(200_000, $low['selected']['by_share']);
        $this->assertSame(265_000, $low['selected']['amount']);
        $this->assertTrue($low['selected']['floor_applied']);

        $this->assertSame(0, $c->calculate([], 1, 1_000_000)['average_monthly']);
    }

    public function test_detect_children_from_params_and_text(): void
    {
        $this->assertSame(3, AlimonyCalculator::detectChildren(['children' => 3]));
        $this->assertSame(2, AlimonyCalculator::detectChildren([], 'Voyaga yetmagan 2 nafar farzand taʼminoti'));
        $this->assertSame(2, AlimonyCalculator::detectChildren([], 'ikki nafar bola bor'));
        $this->assertNull(AlimonyCalculator::detectChildren([], 'Mulk nizosi'));
    }
}

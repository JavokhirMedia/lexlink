<?php

namespace App\Services\Ai;

/**
 * Pure PHP alimony maths (numbers are never produced by the model).
 *
 * Oila kodeksi 99-modda (lex.uz/docs/-104720), verified against the imported text:
 *   1-qism: "... bir bola uchun — toʻrtdan bir qismi; ikki bola uchun — uchdan bir qismi;
 *            uch va undan ortiq bola uchun — yarmisi miqdorida undiriladi."
 *   2-qism: "Har bir bola uchun undiriladigan aliment miqdori ... mehnatga haq toʻlash eng kam
 *            miqdorining 26,5 foizidan kam boʻlmasligi kerak."
 */
class AlimonyCalculator
{
    public const LEGAL_REF = ['slug' => 'oila-kodeksi', 'article' => '99'];

    /** @return array{0: int, 1: int} numerator, denominator */
    public static function share(int $children): array
    {
        return match (true) {
            $children <= 0 => [0, 1],
            $children === 1 => [1, 4],
            $children === 2 => [1, 3],
            default => [1, 2],
        };
    }

    /**
     * @param  list<array{period?: string, amount: int|float|string, employer?: ?string}>  $months  salary rows (several employers per month allowed)
     * @param  int|null  $children  null => table for 1, 2 and 3+ children
     * @return array{
     *   period: array{from: ?string, to: ?string}, months_count: int, total_income: int, average_monthly: int,
     *   monthly: list<array{period: string, amount: int}>, children: ?int, min_wage: int, min_per_child: int,
     *   options: list<array{children: int, label: string, share: string, share_value: float, amount: int, by_share: int, floor_applied: bool}>,
     *   selected: ?array, legal_basis: string
     * }
     */
    public function calculate(array $months, ?int $children = null, ?int $minWage = null): array
    {
        $byPeriod = [];
        foreach ($months as $i => $row) {
            $period = (string) ($row['period'] ?? ('#'.($i + 1)));
            $byPeriod[$period] = ($byPeriod[$period] ?? 0) + (int) round((float) ($row['amount'] ?? 0));
        }
        ksort($byPeriod);

        $count = count($byPeriod);
        $total = array_sum($byPeriod);
        $average = $count > 0 ? intdiv($total + intdiv($count, 2), $count) : 0;

        $minWage ??= (int) config('lexlink.ai.min_wage', 0);
        $minPerChild = (int) round($minWage * (float) config('lexlink.ai.alimony_min_share_of_min_wage', 0.265));

        $options = [];
        foreach ([1, 2, 3] as $n) {
            [$num, $den] = self::share($n);
            $byShare = intdiv($average * $num + intdiv($den, 2), $den);
            $floor = $minPerChild * $n;
            $options[] = [
                'children' => $n,
                'label' => $n >= 3 ? '3 va undan ortiq bola' : "{$n} bola",
                'share' => "{$num}/{$den}",
                'share_value' => round($num / $den, 4),
                'by_share' => $byShare,
                'amount' => max($byShare, $floor),
                'floor_applied' => $floor > $byShare,
            ];
        }

        $selected = null;
        if ($children !== null && $children > 0) {
            $selected = $options[min($children, 3) - 1];
            $selected['children'] = $children;
            if ($children > 3) {
                // floor is per child, so recompute for the exact number of children
                $floor = $minPerChild * $children;
                $selected['amount'] = max($selected['by_share'], $floor);
                $selected['floor_applied'] = $floor > $selected['by_share'];
                $selected['label'] = "{$children} bola";
            }
        }

        return [
            'period' => ['from' => array_key_first($byPeriod), 'to' => array_key_last($byPeriod)],
            'months_count' => $count,
            'total_income' => $total,
            'average_monthly' => $average,
            'monthly' => array_map(fn ($p, $a) => ['period' => (string) $p, 'amount' => $a], array_keys($byPeriod), $byPeriod),
            'children' => $children,
            'min_wage' => $minWage,
            'min_per_child' => $minPerChild,
            'options' => $options,
            'selected' => $selected,
            'legal_basis' => 'Oila kodeksi 99-modda',
        ];
    }

    /**
     * Number of children from case/request params or free text (e.g. "2 nafar farzand", "ikki nafar bola").
     */
    public static function detectChildren(array $params = [], ?string ...$texts): ?int
    {
        foreach (['children', 'children_count', 'child_count', 'bolalar_soni'] as $k) {
            if (isset($params[$k]) && (int) $params[$k] > 0) {
                return (int) $params[$k];
            }
        }

        $words = ['bir' => 1, 'bitta' => 1, 'ikki' => 2, 'ikkita' => 2, 'uch' => 3, 'uchta' => 3, 'toʻrt' => 4, "to'rt" => 4, 'toʻrtta' => 4, "to'rtta" => 4, 'besh' => 5, 'beshta' => 5];
        foreach ($texts as $text) {
            $t = mb_strtolower((string) $text);
            if ($t === '') {
                continue;
            }
            if (preg_match('/(\d+)\s*(?:nafar\s+)?(?:voyaga\s+yetmagan\s+)?(?:farzand|bola)/u', $t, $m)) {
                return (int) $m[1] ?: null;
            }
            if (preg_match('/(?<!\p{L})('.implode('|', array_map(fn ($w) => preg_quote($w, '/'), array_keys($words))).')\s+(?:nafar\s+)?(?:voyaga\s+yetmagan\s+)?(?:farzand|bola)/u', $t, $m)) {
                return $words[$m[1]];
            }
        }

        return null;
    }
}

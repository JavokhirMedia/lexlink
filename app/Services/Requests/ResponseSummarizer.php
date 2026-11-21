<?php

namespace App\Services\Requests;

use App\Enums\DataRequestType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Builds the non-PII summary (counts, totals, period) kept after the full payload is purged.
 * All numbers are computed in PHP — never by the AI.
 */
class ResponseSummarizer
{
    public function summarize(DataRequestType $type, array $result): array
    {
        $data = $result['data'] ?? [];

        return ['type' => $type->value] + match ($type) {
            DataRequestType::Salary => $this->salary($data),
            DataRequestType::Property => $this->property($data),
            DataRequestType::Vehicle => $this->vehicle($data),
            DataRequestType::CourtCase => $this->courtCase($data),
            DataRequestType::TaxInfo => $this->taxInfo($data),
            DataRequestType::BusinessShares => $this->businessShares($data),
            DataRequestType::BankAccounts => $this->bankAccounts($data),
            DataRequestType::BorderCrossing => $this->borderCrossing($data),
        };
    }

    private function salary(array $data): array
    {
        $months = collect($data['months'] ?? []);
        $total = (int) $months->sum(fn ($m) => (int) ($m['amount'] ?? 0));
        $periods = $months->pluck('period')->filter()->unique()->sort()->values();
        $monthsCount = $periods->count();
        $employers = collect($data['employers'] ?? [])->pluck('tin')->filter()->unique()->count()
            ?: $months->pluck('employer')->filter()->unique()->count();

        return [
            'period_from' => $data['period']['from'] ?? $periods->first(),
            'period_to' => $data['period']['to'] ?? $periods->last(),
            'months_count' => $monthsCount,
            'employers_count' => $employers,
            'total' => $total,
            'average_monthly' => $monthsCount ? (int) round($total / $monthsCount) : 0,
            'pension_total' => (int) $months->sum(fn ($m) => (int) ($m['pension_contribution'] ?? 0)),
        ];
    }

    private function property(array $data): array
    {
        $objects = collect($data['objects'] ?? []);

        return [
            'objects_count' => $objects->count(),
            'total_area' => round((float) $objects->sum(fn ($o) => (float) ($o['area'] ?? 0)), 2),
            'types' => $objects->pluck('type')->filter()->countBy()->all(),
            'encumbered_count' => $objects->filter(fn ($o) => ! empty($o['encumbrances']))->count(),
        ];
    }

    private function vehicle(array $data): array
    {
        $vehicles = collect($data['vehicles'] ?? []);

        return [
            'vehicles_count' => $vehicles->count(),
            'pledged_count' => $vehicles->filter(fn ($v) => ! empty($v['pledge']))->count(),
            'newest_year' => $vehicles->max('year'),
        ];
    }

    private function courtCase(array $data): array
    {
        $today = now()->toDateString();
        $next = collect($data['hearings'] ?? [])->pluck('date')->filter(fn ($d) => $d >= $today)->sort()->first();

        return [
            'found' => ! empty($data['case_number']),
            'case_number' => $data['case_number'] ?? null,
            'court' => $data['court'] ?? null,
            'category' => $data['category'] ?? null,
            'status' => $data['status'] ?? null,
            'parties_count' => count($data['parties'] ?? []),
            'hearings_count' => count($data['hearings'] ?? []),
            'decisions_count' => count($data['decisions'] ?? []),
            'next_hearing' => $next,
        ];
    }

    private function taxInfo(array $data): array
    {
        $ie = $data['individual_entrepreneur'] ?? [];

        return [
            'tin_present' => ! empty($data['tin']),
            'tax_regime' => $data['tax_regime'] ?? null,
            'vat_payer' => (bool) ($data['vat_payer'] ?? false),
            'is_individual_entrepreneur' => ! empty($ie['activity']) || str_starts_with((string) ($ie['status'] ?? ''), 'YaTT sifatida'),
            'ie_activity' => $ie['activity'] ?? null,
            'tax_debt' => (int) ($data['tax_debt'] ?? 0),
            'taxes_paid_last_year' => (int) ($data['taxes_paid_last_year'] ?? 0),
            'participations_count' => count($data['participations'] ?? []),
        ];
    }

    private function businessShares(array $data): array
    {
        $companies = collect($data['companies'] ?? []);
        $value = fn ($c) => (int) round((int) ($c['charter_capital'] ?? 0) * (float) ($c['share_percent'] ?? 0) / 100);

        return [
            'companies_count' => $companies->count(),
            'active_count' => $companies->filter(fn ($c) => ($c['status'] ?? null) === 'Faol')->count(),
            'total_share_value' => (int) $companies->sum($value),
            'largest_share_percent' => $companies->max(fn ($c) => (float) ($c['share_percent'] ?? 0)),
        ];
    }

    private function bankAccounts(array $data): array
    {
        $accounts = collect($data['accounts'] ?? []);
        $totals = $accounts->groupBy(fn ($a) => $a['currency'] ?? 'UZS')->map(fn ($g) => [
            'balance' => (int) $g->sum(fn ($a) => (int) ($a['balance'] ?? 0)),
            'turnover_in' => (int) $g->sum(fn ($a) => (int) ($a['turnover_in'] ?? 0)),
            'turnover_out' => (int) $g->sum(fn ($a) => (int) ($a['turnover_out'] ?? 0)),
            'accounts' => $g->count(),
        ])->sortKeys()->all();

        return [
            'accounts_count' => $accounts->count(),
            'banks_count' => $accounts->pluck('bank')->filter()->unique()->count(),
            'totals_by_currency' => $totals,
            'restricted_count' => $accounts->filter(fn ($a) => ! empty($a['restrictions']))->count(),
            'turnover_months' => (int) ($data['turnover_period']['months'] ?? 6),
        ];
    }

    private function borderCrossing(array $data, ?CarbonInterface $now = null): array
    {
        $now ??= now();
        $crossings = collect($data['crossings'] ?? [])->sortBy('date')->values();
        $last = $crossings->last();
        $lastExit = $crossings->where('direction', 'exit')->last() ?? ($data['last_exit'] ?? null);
        $abroad = $last ? ($last['direction'] ?? null) === 'exit' : (bool) ($data['currently_abroad'] ?? false);

        // days abroad within the last 12 months (open exit counts up to today)
        $windowStart = $now->copy()->subYear();
        $days = 0;
        $out = null;
        foreach ($crossings as $c) {
            $at = Carbon::parse($c['date']);
            if (($c['direction'] ?? null) === 'exit') {
                $out = $at;
            } elseif ($out) {
                $days += $this->overlap($out, $at, $windowStart, $now);
                $out = null;
            }
        }
        if ($out) {
            $days += $this->overlap($out, $now, $windowStart, $now);
        }

        return [
            'crossings_count' => $crossings->count(),
            'exits_count' => $crossings->where('direction', 'exit')->count(),
            'currently_abroad' => $abroad,
            'last_exit_date' => $lastExit ? substr((string) $lastExit['date'], 0, 10) : null,
            'last_exit_country' => $lastExit['country'] ?? null,
            'days_abroad_current' => $abroad && $lastExit ? (int) Carbon::parse($lastExit['date'])->startOfDay()->diffInDays($now->copy()->startOfDay()) : 0,
            'days_abroad_last_12m' => $days,
            'countries' => $crossings->pluck('country')->filter()->unique()->values()->all(),
        ];
    }

    private function overlap(CarbonInterface $a, CarbonInterface $b, CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $a->copy()->max($from);
        $end = $b->copy()->min($to);

        return $end->greaterThan($start) ? (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) : 0;
    }
}

<?php

namespace App\Services\Requests;

use App\Enums\DataRequestType;

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
}

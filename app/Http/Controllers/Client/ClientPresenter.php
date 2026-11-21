<?php

namespace App\Http\Controllers\Client;

use App\Enums\DataRequestStatus;
use App\Http\Controllers\Cases\CasePresenter;
use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Support\RequestDeadline;

/**
 * What the client (ishonch bildiruvchi) may see: metadata, status timeline and — for completed
 * requests — ONLY the non-PII summary + the (masked) AI summary. Never the payload, PDF or PINFLs.
 */
final class ClientPresenter
{
    public const NOTE = "To'liq javob advokatda saqlanadi — advokat siri va shaxsga doir ma'lumotlarni minimallashtirish (ShMQ) talabi.";

    /** Statuses visible to the client (drafts are the advocate's internal work). */
    public const VISIBLE = [
        DataRequestStatus::Submitted, DataRequestStatus::Processing, DataRequestStatus::Completed,
        DataRequestStatus::Rejected, DataRequestStatus::Failed,
    ];

    private const LABELS = [
        'period_from' => 'Davr boshi',
        'period_to' => 'Davr oxiri',
        'months_count' => 'Oylar soni',
        'employers_count' => 'Ish beruvchilar soni',
        'total' => 'Jami daromad',
        'average_monthly' => "O'rtacha oylik daromad",
        'pension_total' => 'Pensiya badallari jami',
        'objects_count' => "Ko'chmas mulk obyektlari",
        'total_area' => 'Umumiy maydon, m²',
        'types' => 'Turlari',
        'encumbered_count' => 'Taqiq/garovdagi obyektlar',
        'vehicles_count' => 'Transport vositalari',
        'pledged_count' => 'Garovdagi transport',
        'newest_year' => 'Eng yangi ishlab chiqarilgan yil',
        'found' => 'Sud ishi topildi',
        'case_number' => 'Ish raqami',
        'court' => 'Sud',
        'category' => 'Toifa',
        'status' => 'Holati',
        'parties_count' => 'Taraflar soni',
        'hearings_count' => 'Sud majlislari',
        'decisions_count' => 'Qarorlar',
        'next_hearing' => 'Navbatdagi majlis',
        'companies_count' => 'Yuridik shaxslar',
        'active_count' => 'Faol yuridik shaxslar',
        'total_share_value' => 'Ulushlar qiymati',
        'largest_share_percent' => 'Eng katta ulush, %',
        'tax_regime' => 'Soliq rejimi',
        'vat_payer' => "QQS to'lovchi",
        'tax_debt' => 'Soliq qarzi',
        'taxes_paid_last_year' => "O'tgan yil to'langan soliqlar",
        'participations_count' => 'Ishtirok etgan yuridik shaxslar',
        'is_individual_entrepreneur' => 'YaTT',
        'accounts_count' => 'Hisobvaraqlar soni',
        'trips_count' => 'Chegaradan o\'tishlar',
        'currently_abroad' => 'Hozir chet elda',
    ];

    /** Keys that hold money (so'm). */
    private const MONEY = ['total', 'average_monthly', 'pension_total', 'total_share_value', 'tax_debt', 'taxes_paid_last_year', 'total_balance', 'turnover'];

    /** Keys never shown to the client even if present in a summary. */
    private const HIDDEN = ['type', 'subject', 'pinfl', 'subject_pinfl', 'full_name', 'tin', 'ie_activity', 'tin_present'];

    public static function legalCase(LegalCase $c): array
    {
        $profile = $c->owner?->advocateProfile;

        return [
            'id' => $c->id,
            'title' => $c->title,
            'court_case_number' => $c->court_case_number,
            'category_label' => CasePresenter::CATEGORIES[$c->category] ?? null,
            'status' => $c->status,
            'advocate' => [
                'name' => $c->owner?->name,
                'license_number' => $profile?->license_number,
                'bureau_name' => $profile?->bureau_name,
            ],
        ];
    }

    public static function request(DataRequest $r): array
    {
        $completed = $r->status === DataRequestStatus::Completed;
        $summary = $completed ? (array) ($r->response?->summary ?? []) : [];
        $ai = $completed
            ? AiAnalysis::where('data_request_id', $r->id)->where('kind', 'summary')->whereNotNull('output')->latest('id')->first()
            : null;

        return [
            'id' => $r->id,
            'type_label' => $r->type->label(),
            'agency' => $r->type->agency(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'completed_at' => $r->completed_at?->toIso8601String(),
            'due_at' => $r->due_at?->toIso8601String(),
            'deadline_label' => RequestDeadline::label($r->deadline_basis),
            'answered_on_time' => $r->due_at && $r->completed_at ? $r->completed_at->lte($r->due_at) : null,
            'rejection_reason' => $r->status === DataRequestStatus::Rejected ? $r->rejection_reason : null,
            'steps' => self::steps($r),
            'summary_items' => self::summaryItems($summary),
            // AI text stays MASKED ([SHAXS_1] …) for the client — placeholders are not restored.
            'ai_summary' => $ai?->output,
        ];
    }

    /** @return list<array{label: string, state: string, at: ?string}> */
    private static function steps(DataRequest $r): array
    {
        $final = match ($r->status) {
            DataRequestStatus::Rejected => ['Rad etildi', 'danger'],
            DataRequestStatus::Failed => ['Texnik xato', 'danger'],
            default => ['Javob olindi', 'success'],
        };
        $isFinal = $r->status->isFinal();

        return [
            ['label' => 'Advokat imzolab yubordi', 'state' => 'done', 'at' => $r->submitted_at?->toIso8601String()],
            ['label' => 'Idorada ko\'rib chiqilmoqda', 'state' => $isFinal ? 'done' : 'current', 'at' => null],
            ['label' => $final[0], 'state' => $isFinal ? $final[1] : 'upcoming', 'at' => $r->completed_at?->toIso8601String()],
        ];
    }

    /** @return list<array{label: string, value: string}> */
    private static function summaryItems(array $summary): array
    {
        $out = [];
        foreach ($summary as $key => $value) {
            if (in_array($key, self::HIDDEN, true) || $value === null || $value === '') {
                continue;
            }
            $label = self::LABELS[$key] ?? ucfirst(str_replace('_', ' ', (string) $key));
            $out[] = ['label' => $label, 'value' => self::format($key, $value)];
        }

        return $out;
    }

    private static function format(string $key, mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Ha' : "Yo'q",
            is_array($value) => collect($value)->map(fn ($v, $k) => is_int($k) ? (string) (is_scalar($v) ? $v : json_encode($v)) : "{$k}: ".(is_scalar($v) ? $v : json_encode($v)))->implode(', '),
            in_array($key, self::MONEY, true) && is_numeric($value) => number_format((float) $value, 0, '.', ' ')." so'm",
            default => (string) $value,
        };
    }
}

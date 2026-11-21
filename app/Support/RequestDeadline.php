<?php

namespace App\Support;

use App\Enums\LegalBasisType;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Services\Gov\GovDataProvider;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Answer deadline for an advocate request — Advokatura to'g'risidagi Qonun 7¹-modda:
 *   15 calendar days from receipt (default);
 *   3 working days when a document proving that inquiry / investigation / court proceedings
 *   have started is attached (here: the case's court case number exists in the court registry).
 * Court-decision based requests get the default 15 days (deadline_basis = court_decision).
 */
final class RequestDeadline
{
    public const LAW_REF = "Advokatura to'g'risidagi Qonun 7¹-modda";

    /** @return array{due_at: CarbonImmutable, deadline_basis: string} */
    public static function compute(DataRequest $r, ?LegalBasis $basis, CarbonInterface $receivedAt, ?GovDataProvider $gov = null): array
    {
        $from = CarbonImmutable::instance($receivedAt);
        $days = (int) LegalMatrix::DEADLINES['default_days'];

        if ($basis?->type === LegalBasisType::AdvocateOrder) {
            if (self::proceedingsStarted($r->legalCase?->court_case_number, $gov)) {
                return [
                    'due_at' => $from->addWeekdays((int) LegalMatrix::DEADLINES['proceedings_work_days'])->endOfDay(),
                    'deadline_basis' => 'proceedings',
                ];
            }

            return ['due_at' => $from->addDays($days)->endOfDay(), 'deadline_basis' => 'default'];
        }

        return ['due_at' => $from->addDays($days)->endOfDay(), 'deadline_basis' => 'court_decision'];
    }

    public static function label(?string $basis): ?string
    {
        return match ($basis) {
            'proceedings' => '3 ish kuni — sud ishi boshlanganligi tasdiqlangan ('.self::LAW_REF.')',
            'default' => '15 kun ('.self::LAW_REF.')',
            'court_decision' => '15 kun — sud ajrimi asosida ('.self::LAW_REF.' bo\'yicha umumiy muddat)',
            default => null,
        };
    }

    private static function proceedingsStarted(?string $courtCaseNumber, ?GovDataProvider $gov): bool
    {
        if (! $courtCaseNumber || ! $gov) {
            return false;
        }
        try {
            return $gov->courtCase($courtCaseNumber) !== null;
        } catch (Throwable) {
            return false; // registry unavailable — fall back to the default 15 days
        }
    }
}

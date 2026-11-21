<?php

namespace App\Services\Ai\Anonymization;

use App\Enums\DataRequestType;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Allowlist projection of an agency response for the AI: only analysis-relevant facts, every identifier masked.
 * Nothing is copied by default — every output field is chosen here explicitly.
 *
 *  people          -> [SHAXS_n]  (incl. judges)        organisations -> [ISH_BERUVCHI_n] / [KORXONA_n] / [BANK_n]
 *  courts          -> generic court type               case / decision -> [ISH_n] / [QAROR_n]
 *  addresses -> [MANZIL_n]   plates -> [AVTORAQAM_n]   VIN -> [VIN_n]   cadastral -> [KADASTR_n]
 *  accounts / cards -> [HISOBRAQAM_n]   TIN -> [STIR_n]   party JShShIR -> [JSHSHIR_n]   birth dates -> [TUGILGAN_SANA_n]
 *  amounts, event dates (periods, registrations, crossings, decisions), countries, checkpoints -> kept
 *  durations (days) are additionally computed in PHP so the model never does date arithmetic
 *
 * Before projecting, every identifying leaf of the raw payload is registered as known PII in the context,
 * so the leak guard blocks the call if any of it shows up in the outgoing text anyway.
 */
class ResponseProjector
{
    public function project(DataRequestType $type, array $data, AnonymizationContext $ctx): array
    {
        $ctx->collect($data);
        if ($data === []) {
            return ['natija' => "ma'lumot topilmadi"];
        }

        return match ($type) {
            DataRequestType::Salary => $this->salary($data, $ctx),
            DataRequestType::Property => $this->property($data, $ctx),
            DataRequestType::Vehicle => $this->vehicle($data, $ctx),
            DataRequestType::CourtCase => $this->courtCase($data, $ctx),
            DataRequestType::TaxInfo => $this->taxInfo($data, $ctx),
            DataRequestType::BusinessShares => $this->businessShares($data, $ctx),
            DataRequestType::BankAccounts => $this->bankAccounts($data, $ctx),
            DataRequestType::BorderCrossing => $this->borderCrossing($data, $ctx),
        };
    }

    /** PHP-computed figures (ResponseSummarizer) made safe for the prompt: identifiers -> placeholders. */
    public function numbers(?array $numbers, AnonymizationContext $ctx): ?array
    {
        if ($numbers === null) {
            return null;
        }
        unset($numbers['case_number'], $numbers['court']);
        array_walk_recursive($numbers, function (&$v) use ($ctx) {
            if (is_string($v)) {
                $v = $ctx->masker->mask($v);
            }
        });

        return $numbers;
    }

    // ------------------------------------------------------------------ per type

    private function salary(array $data, AnonymizationContext $ctx): array
    {
        $months = collect($data['months'] ?? []);
        $tins = collect($data['employers'] ?? [])->mapWithKeys(fn ($e) => [(string) ($e['name'] ?? '') => $e['tin'] ?? null]);
        $employers = $tins->keys()->merge($months->pluck('employer'))->filter()->unique()->values();

        return [
            'davr' => ['dan' => $data['period']['from'] ?? $months->min('period'), 'gacha' => $data['period']['to'] ?? $months->max('period')],
            'ish_beruvchilar' => $employers->map(function (string $name) use ($months, $ctx, $tins) {
                $rows = $months->where('employer', $name);

                return array_filter([
                    'nomi' => $ctx->organization('ISH_BERUVCHI', $name),
                    'stir' => $this->id($ctx, 'STIR', 'tin', $tins[$name] ?? null),
                    'oylar_soni' => $rows->pluck('period')->unique()->count(),
                    'jami' => (int) $rows->sum('amount'),
                    'dan' => $rows->min('period'),
                    'gacha' => $rows->max('period'),
                ], fn ($v) => $v !== null);
            })->all(),
            'oylik_daromad' => $months->map(fn ($m) => [
                'oy' => $m['period'] ?? null,
                'ish_beruvchi' => $ctx->organization('ISH_BERUVCHI', $m['employer'] ?? null),
                'summa' => (int) ($m['amount'] ?? 0),
                'pensiya_badali' => (int) ($m['pension_contribution'] ?? 0),
            ])->values()->all(),
            'jami' => (int) ($data['total'] ?? $months->sum('amount')),
            'pensiya_badallari_jami' => (int) $months->sum(fn ($m) => (int) ($m['pension_contribution'] ?? 0)),
        ];
    }

    private function property(array $data, AnonymizationContext $ctx): array
    {
        return ['obyektlar' => array_map(fn (array $o) => [
            'kadastr_raqami' => $this->id($ctx, 'KADASTR', 'cadastral', $o['cadastral_number'] ?? null),
            'turi' => $o['type'] ?? null,
            'manzil' => $ctx->address($o['address'] ?? null),
            'maydon_m2' => $o['area'] ?? null,
            'ulush' => $o['share'] ?? null,
            'royxatdan_otgan' => $o['registered_at'] ?? null,
            'cheklovlar' => $this->encumbrances($o['encumbrances'] ?? [], $ctx),
        ], array_values($data['objects'] ?? []))];
    }

    private function vehicle(array $data, AnonymizationContext $ctx): array
    {
        return ['transport_vositalari' => array_map(function (array $v) use ($ctx) {
            $pledge = $v['pledge'] ?? null;

            return [
                'rusumi' => $v['model'] ?? null,
                'yili' => $v['year'] ?? null,
                'davlat_raqami' => $this->id($ctx, 'AVTORAQAM', 'plate', $v['plate'] ?? null),
                'vin' => $this->id($ctx, 'VIN', 'vin', $v['vin'] ?? null),
                'rangi' => $v['color'] ?? null,
                'royxatdan_otgan' => $v['registered_at'] ?? null,
                'garov' => match (true) {
                    empty($pledge) => null,
                    is_array($pledge) => ['garovga_oluvchi' => $this->orgRef($pledge['holder'] ?? $pledge['by'] ?? null, $ctx), 'dan' => $pledge['since'] ?? null],
                    default => ['mavjud' => true],
                },
            ];
        }, array_values($data['vehicles'] ?? []))];
    }

    private function courtCase(array $data, AnonymizationContext $ctx): array
    {
        $ctx->generalize('sud nomi umumlashtirildi');

        return [
            'ish_raqami' => $this->id($ctx, 'ISH', 'case_number', $data['case_number'] ?? null),
            'sud' => self::court($data['court'] ?? null),
            'sudya' => isset($data['judge']) ? ($ctx->person($data['judge']) ?? '[SHAXS]') : null,
            'toifa' => $data['category'] ?? null,
            'holati' => $data['status'] ?? null,
            'taraflar' => array_map(fn (array $p) => [
                'roli' => $p['role'] ?? null,
                'taraf' => AnonymizationContext::looksLikeOrganization((string) ($p['name'] ?? ''))
                    ? $ctx->organization('KORXONA', $p['name'])
                    : ($ctx->person($p['name'] ?? null) ?? '[SHAXS]'),
                'jshshir' => isset($p['pinfl']) ? $ctx->masker->mask((string) $p['pinfl']) : null,
            ], array_values($data['parties'] ?? [])),
            'sud_majlislari' => array_map(fn (array $h) => [
                'sana' => $h['date'] ?? null,
                'vaqt' => $h['time'] ?? null,
                'necha_kundan_keyin' => self::daysFromToday($h['date'] ?? null),
                'izoh' => isset($h['note']) ? $ctx->masker->mask((string) $h['note']) : null,
            ], array_values($data['hearings'] ?? [])),
            'sud_hujjatlari' => array_map(fn (array $d) => [
                'raqami' => $this->id($ctx, 'QAROR', 'decision_number', $d['number'] ?? null),
                'turi' => $d['type'] ?? null,
                'sana' => $d['date'] ?? null,
                'necha_kun_oldin' => ($n = self::daysFromToday($d['date'] ?? null)) === null ? null : -$n,
                'mazmuni' => isset($d['summary']) ? $ctx->masker->mask((string) $d['summary']) : null,
            ], array_values($data['decisions'] ?? [])),
        ];
    }

    private function taxInfo(array $data, AnonymizationContext $ctx): array
    {
        $ie = (array) ($data['individual_entrepreneur'] ?? []);

        return [
            'stir' => $this->id($ctx, 'STIR', 'tin', $data['tin'] ?? null),
            'soliq_rejimi' => $data['tax_regime'] ?? null,
            'qqs_tolovchi' => (bool) ($data['vat_payer'] ?? false),
            'yatt' => ['holati' => $ie['status'] ?? null, 'faoliyat_turi' => $ie['activity'] ?? null],
            'soliq_qarzi' => (int) ($data['tax_debt'] ?? 0),
            'otgan_yili_tolangan_soliqlar' => (int) ($data['taxes_paid_last_year'] ?? 0),
            'hisobga_olingan' => $data['registered_at'] ?? null,
            'yuridik_shaxslardagi_ishtirok' => array_map(fn (array $c) => [
                'korxona' => $ctx->organization('KORXONA', $c['name'] ?? null),
                'stir' => $this->id($ctx, 'STIR', 'tin', $c['tin'] ?? null),
                'ulush_foiz' => $c['share_percent'] ?? null,
                'holati' => $c['status'] ?? null,
            ], array_values($data['participations'] ?? [])),
        ];
    }

    private function businessShares(array $data, AnonymizationContext $ctx): array
    {
        return ['korxonalar' => array_map(fn (array $c) => [
            'korxona' => $ctx->organization('KORXONA', $c['name'] ?? null),
            'stir' => $this->id($ctx, 'STIR', 'tin', $c['tin'] ?? null),
            'ulush_foiz' => $c['share_percent'] ?? null,
            'ustav_fondi' => (int) ($c['charter_capital'] ?? 0),
            'ulush_qiymati' => (int) ($c['share_value'] ?? 0),
            'ishtirokchi_bolgan' => $c['since'] ?? null,
            'holati' => $c['status'] ?? null,
        ], array_values($data['companies'] ?? []))];
    }

    private function bankAccounts(array $data, AnonymizationContext $ctx): array
    {
        return [
            'hisobvaraqlar' => array_map(fn (array $a) => [
                'bank' => $ctx->organization('BANK', $a['bank'] ?? null),
                'hisob_raqami' => $this->id($ctx, 'HISOBRAQAM', 'account', $a['account'] ?? $a['account_mask'] ?? null),
                'valyuta' => $a['currency'] ?? null,
                'qoldiq' => (int) ($a['balance'] ?? 0),
                'kirim' => (int) ($a['turnover_in'] ?? 0),
                'chiqim' => (int) ($a['turnover_out'] ?? 0),
                'ochilgan' => $a['opened_at'] ?? null,
                'cheklovlar' => $this->encumbrances($a['restrictions'] ?? [], $ctx),
            ], array_values($data['accounts'] ?? [])),
            'valyuta_boyicha_jami' => $data['totals_by_currency'] ?? [],
            'aylanma_davri' => $data['turnover_period'] ?? null,
            'holat_sanasi' => $data['as_of'] ?? null,
        ];
    }

    private function borderCrossing(array $data, AnonymizationContext $ctx): array
    {
        $crossings = collect($data['crossings'] ?? [])->sortBy('date')->values();
        $trips = [];
        $out = null;
        foreach ($crossings as $c) {
            if (($c['direction'] ?? null) === 'exit') {
                $out = $c;
            } elseif ($out) {
                $trips[] = ['davlat' => $out['country'] ?? null, 'chiqish' => $out['date'] ?? null, 'kirish' => $c['date'] ?? null, 'kunlar' => self::daysBetween($out['date'] ?? null, $c['date'] ?? null)];
                $out = null;
            }
        }
        $lastExit = $data['last_exit'] ?? $crossings->where('direction', 'exit')->last();

        return [
            'otishlar' => $crossings->map(fn ($c) => [
                'yonalish' => ($c['direction'] ?? null) === 'exit' ? 'chiqish' : 'kirish',
                'sana' => $c['date'] ?? null,
                'davlat' => $c['country'] ?? null,
                'otish_punkti' => $c['checkpoint'] ?? null,
            ])->all(),
            'safarlar' => $trips,
            'hozir_chet_elda' => (bool) ($data['currently_abroad'] ?? false),
            'songgi_chiqish' => $lastExit ? [
                'sana' => $lastExit['date'] ?? null,
                'davlat' => $lastExit['country'] ?? null,
                'necha_kun_oldin' => ($n = self::daysFromToday($lastExit['date'] ?? null)) === null ? null : -$n,
            ] : null,
            'songgi_12_oyda_chet_elda_kunlar' => (int) ($data['days_abroad_last_12m'] ?? 0),
            'davr' => $data['period'] ?? null,
        ];
    }

    /** Identifier -> restorable placeholder ([KADASTR_1], [STIR_1] ...); null stays null. */
    private function id(AnonymizationContext $ctx, string $kind, string $category, mixed $value): ?string
    {
        return is_scalar($value) && trim((string) $value) !== '' ? $ctx->identifier($kind, $category, (string) $value) : null;
    }

    // ------------------------------------------------------------------ helpers

    /** Encumbrances / restrictions: kind + who (pseudonymised / generic public body) + month. */
    private function encumbrances(mixed $items, AnonymizationContext $ctx): array
    {
        $out = [];
        foreach ((array) $items as $e) {
            if (is_string($e)) {
                $out[] = ['turi' => $ctx->masker->mask($e)];

                continue;
            }
            if (! is_array($e)) {
                continue;
            }
            $out[] = array_filter([
                'turi' => isset($e['type']) ? $ctx->masker->mask((string) $e['type']) : 'cheklov',
                'kim_tomonidan' => $this->orgRef($e['holder'] ?? $e['by'] ?? null, $ctx),
                'dan' => $e['since'] ?? $e['date'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    /** Organisation reference: courts / enforcement bureau stay generic public bodies; banks / companies get a pseudonym. */
    private function orgRef(?string $name, AnonymizationContext $ctx): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }
        if (preg_match('/\bsud/iu', $name)) {
            return self::court($name);
        }
        if (preg_match('/ijro\s+byuro/iu', $name)) {
            return 'Majburiy ijro byurosi';
        }
        $ctx->known('organization', $name);

        return $ctx->organization(preg_match('/bank|ATB|\bAJ\b/iu', $name) ? 'BANK' : 'KORXONA', $name);
    }

    /** "Toshkent shahar Yunusobod tumanlararo fuqarolik ishlari sudi" -> "tumanlararo fuqarolik ishlari sudi". */
    public static function court(?string $name): ?string
    {
        $n = mb_strtolower(LeakGuard::unifyApostrophes((string) $name));
        if ($n === '') {
            return null;
        }

        return match (true) {
            str_contains($n, 'oliy sud') => 'Oliy sud',
            str_contains($n, 'iqtisodiy') => 'iqtisodiy sud',
            str_contains($n, "ma'muriy") => "ma'muriy sud",
            str_contains($n, 'jinoyat') => "jinoyat ishlari bo'yicha sud",
            str_contains($n, 'viloyat') || str_contains($n, 'shahar sudi') => 'viloyat (shahar) sudi',
            default => "tumanlararo fuqarolik ishlari bo'yicha sud",
        };
    }

    /** Whole days from today to $date (negative = in the past). */
    public static function daysFromToday(mixed $date): ?int
    {
        try {
            return is_string($date) && $date !== '' ? (int) now()->startOfDay()->diffInDays(Carbon::parse($date)->startOfDay(), false) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function daysBetween(mixed $from, mixed $to): ?int
    {
        try {
            return is_string($from) && is_string($to) ? (int) Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) : null;
        } catch (Throwable) {
            return null;
        }
    }
}

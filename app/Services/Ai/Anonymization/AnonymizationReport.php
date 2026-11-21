<?php

namespace App\Services\Ai\Anonymization;

/**
 * Transparency report persisted on ai_analyses.anonymization_report and shown in "AI'ga nima yuborildi?".
 * Never contains values or the reverse map — only categories, counts and field labels.
 */
final class AnonymizationReport
{
    public const LEGAL_BASIS = ['ShMQ 16-modda', 'ShMQ 15-modda'];

    /**
     * @param  array<string, int>  $replaced  placeholder kind => distinct placeholders in the sent text
     * @param  list<string>  $dropped
     * @param  list<string>  $generalized
     * @param  array<string, int>  $blocked  leak category => hits
     */
    public static function make(string $leakCheck, bool $aiCalled, array $replaced = [], array $dropped = [], array $generalized = [], array $blocked = [], ?string $note = null): array
    {
        $groups = [];
        foreach ($replaced as $kind => $n) {
            $label = PiiCategory::label($kind);
            $groups[$label] = ($groups[$label] ?? 0) + $n;
        }

        return array_filter([
            'leak_check' => $leakCheck, // passed | blocked | not_run
            'ai_called' => $aiCalled,
            'legal_basis' => self::LEGAL_BASIS,
            'replaced' => array_map(fn ($label, $n) => ['label' => $label, 'count' => $n], array_keys($groups), array_values($groups)),
            'replaced_by_kind' => $replaced,
            'dropped' => array_values(array_unique($dropped)),
            'generalized' => array_values(array_unique($generalized)),
            'blocked' => $blocked === [] ? null : array_map(fn ($cat, $n) => ['category' => $cat, 'label' => PiiCategory::label($cat), 'count' => $n], array_keys($blocked), array_values($blocked)),
            'note' => $note,
        ], fn ($v) => $v !== null);
    }

    /** Report for calls that never reached Gemini (no key / FallbackAiService). */
    public static function notCalled(?AnonymizationContext $ctx = null, string $note = "AI chaqirilmadi — javob mahalliy shablon asosida tayyorlandi, hech qanday ma'lumot tashqariga yuborilmadi."): array
    {
        return self::make('not_run', false, [], $ctx?->droppedFields() ?? [], $ctx?->generalizedFields() ?? [], [], $note);
    }

    /** @return array<string, int> placeholder kind => number of distinct placeholders */
    public static function countPlaceholders(string $text): array
    {
        preg_match_all('/\[([A-Z]+(?:_[A-Z]+)*)_(\d+)\]/', $text, $m, PREG_SET_ORDER);
        $seen = [];
        foreach ($m as $hit) {
            $seen[$hit[1]][$hit[2]] = true;
        }

        return array_map('count', $seen);
    }
}

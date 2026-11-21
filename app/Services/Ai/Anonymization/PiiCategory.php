<?php

namespace App\Services\Ai\Anonymization;

/**
 * Placeholder kinds used in anonymised prompts ([KIND_n]) and their Uzbek labels for the transparency report.
 */
final class PiiCategory
{
    public const LABELS = [
        'SHAXS' => 'shaxs',
        'JSHSHIR' => 'JShShIR',
        'PASPORT' => 'pasport raqami',
        'TELEFON' => 'telefon raqami',
        'EMAIL' => 'e-pochta',
        'MANZIL' => 'manzil',
        'AVTORAQAM' => 'davlat raqami',
        'VIN' => 'VIN',
        'KADASTR' => 'kadastr raqami',
        'HISOBRAQAM' => 'hisob raqami',
        'STIR' => 'STIR',
        'ISH_BERUVCHI' => 'tashkilot',
        'KORXONA' => 'tashkilot',
        'BANK' => 'tashkilot',
        'SUD' => 'sud nomi',
        'ISH' => 'sud ishi raqami',
        'QAROR' => 'sud qarori raqami',
        'ORDER' => 'order raqami',
        'SOROV' => "so'rov raqami",
        'LITSENZIYA' => 'litsenziya raqami',
        'TUGILGAN_SANA' => "tug'ilgan sana",
    ];

    /** Categories reported by the leak guard (never with values). */
    public const LEAK_LABELS = [
        'person_name' => 'F.I.Sh.',
        'pinfl' => 'JShShIR',
        'masked_id' => 'qisman yashirilgan identifikator',
        'passport' => 'pasport raqami',
        'phone' => 'telefon raqami',
        'email' => 'e-pochta',
        'plate' => 'davlat raqami',
        'vin' => 'VIN',
        'cadastral' => 'kadastr raqami',
        'account' => 'hisob / karta raqami',
        'tin' => 'STIR',
        'organization' => 'tashkilot nomi',
        'address' => 'manzil',
        'case_number' => 'sud ishi raqami',
        'decision_number' => 'qaror / order raqami',
        'request_number' => "so'rov raqami",
        'license' => 'litsenziya raqami',
        'birth_date' => "tug'ilgan sana",
        'court_name' => 'sud nomi',
    ];

    public static function label(string $kind): string
    {
        return self::LABELS[$kind] ?? self::LEAK_LABELS[$kind] ?? mb_strtolower($kind);
    }
}

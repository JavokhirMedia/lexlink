<?php

namespace App\Services\Ai\Lex;

/**
 * Laws imported from lex.uz (official legislation database of Uzbekistan).
 * `lex_id` is the Uzbek-Latin document id (lex.uz serves Latin under the negative id: /docs/-54503).
 * `cyrillic_id` / `russian_id` are kept for reference only.
 */
class LexCatalog
{
    public const BASE_URL = 'https://lex.uz';

    /**
     * @return list<array{slug: string, lex_id: string, cyrillic_id: ?string, short_title: string, title: string, only?: list<string>}>
     */
    public static function documents(): array
    {
        return [
            ['slug' => 'shaxsga-doir-malumotlar', 'lex_id' => '-4396419', 'cyrillic_id' => '4396419', 'short_title' => "ShMQ (O'RQ-547)", 'title' => "Shaxsga doir ma'lumotlar to'g'risida Qonun (O'RQ-547, 02.07.2019)"],
            ['slug' => 'advokatura', 'lex_id' => '-54503', 'cyrillic_id' => '54503', 'short_title' => "Advokatura to'g'risida Qonun", 'title' => "Advokatura to'g'risida Qonun (349-I, 27.12.1996)"],
            ['slug' => 'advokatlik-kafolatlari', 'lex_id' => '-29626', 'cyrillic_id' => '29626', 'short_title' => 'Advokatlik kafolatlari Qonuni', 'title' => "Advokatlik faoliyatining kafolatlari va advokatlarning ijtimoiy himoyasi to'g'risida Qonun (721-I, 25.12.1998)"],
            ['slug' => 'oila-kodeksi', 'lex_id' => '-104720', 'cyrillic_id' => '104720', 'short_title' => 'Oila kodeksi', 'title' => "O'zbekiston Respublikasining Oila kodeksi (30.04.1998)"],
            ['slug' => 'fuqarolik-protsessual-kodeksi', 'lex_id' => '-3517337', 'cyrillic_id' => '3517337', 'short_title' => 'FPK', 'title' => "O'zbekiston Respublikasining Fuqarolik protsessual kodeksi (22.01.2018)"],
            ['slug' => 'elektron-raqamli-imzo', 'lex_id' => '-6234904', 'cyrillic_id' => '6234904', 'short_title' => "ERI to'g'risida Qonun", 'title' => "Elektron raqamli imzo to'g'risida Qonun (O'RQ-793, 12.10.2022)"],
            ['slug' => 'axborotlashtirish', 'lex_id' => '-83472', 'cyrillic_id' => '83472', 'short_title' => "Axborotlashtirish to'g'risida Qonun", 'title' => "Axborotlashtirish to'g'risida Qonun (560-II, 11.12.2003)"],
            ['slug' => 'soliq-kodeksi', 'lex_id' => '-4674902', 'cyrillic_id' => '4674902', 'short_title' => 'Soliq kodeksi', 'title' => "O'zbekiston Respublikasining Soliq kodeksi (30.12.2019)", 'only' => ['29']],
            ['slug' => 'bank-siri', 'lex_id' => '-41760', 'cyrillic_id' => '41760', 'short_title' => "Bank siri to'g'risida Qonun", 'title' => "Bank siri to'g'risida Qonun (530-II, 30.08.2003)"],
            ['slug' => 'mehnat-kodeksi', 'lex_id' => '-6257288', 'cyrillic_id' => null, 'short_title' => 'Mehnat kodeksi', 'title' => "O'zbekiston Respublikasining Mehnat kodeksi (28.10.2022)"],
        ];
    }

    public static function docUrl(string $lexId): string
    {
        return self::BASE_URL.'/docs/'.$lexId;
    }

    public static function articleUrl(?string $docUrl, ?string $anchor): ?string
    {
        if (! $docUrl) {
            return null;
        }

        return $anchor ? $docUrl.'#'.$anchor : $docUrl;
    }

    public static function snapshotPath(): string
    {
        return storage_path('app/lex/laws.json');
    }

    public static function rawPath(string $lexId): string
    {
        return storage_path('app/lex/raw/'.$lexId.'.html');
    }
}

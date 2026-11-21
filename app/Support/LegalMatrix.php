<?php

namespace App\Support;

use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;

/**
 * "Huquqiy xarita" — SINGLE SOURCE OF TRUTH for what an advocate may obtain, grouped by domain (soha),
 * which legal basis each data type needs, and why (law references verified against the lex.uz snapshot
 * in storage/app/lex/laws.json unless marked `verify`).
 *
 * Used by: DataRequestType::acceptedBases(), DataRequestService::checkBasis(), request form, /legal-map page.
 *
 * access:
 *   'order' — advokat so'rovi (Advokatura to'g'risidagi Qonun 7¹-modda) + advokat orderi yetarli; sud qarori ham qabul qilinadi
 *   'court' — faqat sud qarori / ajrimi (sir rejimi yoki maxsus toifadagi shaxsga doir ma'lumot)
 */
final class LegalMatrix
{
    public const DISCLAIMER = 'Xarita hakaton demosi uchun lex.uz matnlari asosida tuzilgan. Aniq tartib Adliya vazirligi va Advokatlar palatasi mentorlari bilan tasdiqlanishi kerak.';

    /** Advocate request deadlines — Advokatura to'g'risidagi Qonun 7¹-modda (verified). */
    public const DEADLINES = [
        'default_days' => 15,          // so'rov olingan sanadan e'tiboran 15 kun
        'no_info_notice_days' => 5,    // ma'lumot bo'lmasa 5 kun ichida xabar
        'proceedings_work_days' => 3,  // surishtiruv/tergov/sud boshlanganligi hujjati ilova qilinsa — 3 ish kuni
    ];

    /** Law reference helper (anchors = lex.uz element ids from the snapshot). */
    private static function ref(string $law, ?string $article, string $note, ?string $url = null): array
    {
        return ['law' => $law, 'article' => $article, 'note' => $note, 'url' => $url];
    }

    private const ADVOKATURA = 'https://lex.uz/docs/-54503';

    private const SHMQ = 'https://lex.uz/docs/-4396419';

    private const SOLIQ = 'https://lex.uz/docs/-4674902';

    /** @return list<array<string, mixed>> */
    public static function domains(): array
    {
        return [
            [
                'key' => 'court',
                'title' => 'Sud ishlari',
                'summary' => "Ish holati, sudya, majlis sanalari va qarorlar ro'yxati.",
                'types' => [DataRequestType::CourtCase->value],
            ],
            [
                'key' => 'tax',
                'title' => 'Soliq va daromad',
                'summary' => "Soliq kodeksining 29-moddasi soliq ma'lumotini ikkiga ajratadi: ochiq qism (advokat so'rovi bilan) va soliq siri (faqat sud orqali).",
                'types' => [DataRequestType::TaxInfo->value, DataRequestType::Salary->value],
            ],
            [
                'key' => 'property',
                'title' => 'Mulkiy huquqlar',
                'summary' => "Ko'chmas mulk, avtotransport va yuridik shaxslardagi ulushlar — undiruv qaratish mumkin bo'lgan mol-mulk.",
                'types' => [DataRequestType::Property->value, DataRequestType::Vehicle->value, DataRequestType::BusinessShares->value],
            ],
            [
                'key' => 'bank',
                'title' => 'Bank siri',
                'summary' => "Hisobvaraqlar, qoldiqlar va aylanmalar bank siri hisoblanadi — advokat so'rovi yetarli emas.",
                'types' => [DataRequestType::BankAccounts->value],
            ],
            [
                'key' => 'border',
                'title' => 'Chetga chiqish',
                'summary' => "Chegaradan o'tish ma'lumotlari shaxsiy hayotga taalluqli maxsus ma'lumot — faqat sud orqali (masalan, aliment qarzdori chet elda ekanini isbotlash).",
                'types' => [DataRequestType::BorderCrossing->value],
            ],
        ];
    }

    /** @return array<string, array<string, mixed>> keyed by DataRequestType value */
    public static function types(): array
    {
        $advRequest = self::ref("Advokatura to'g'risidagi Qonun", '7¹', "Advokat so'rovi: axborot 15 kun ichida beriladi", self::ADVOKATURA.'#-4013524');
        $advRights = self::ref("Advokatura to'g'risidagi Qonun", '6', "Advokat ma'lumotnoma va hujjatlarni so'rash va olishga haqli", self::ADVOKATURA.'#-55655');
        $shmq18 = self::ref("Shaxsga doir ma'lumotlar to'g'risidagi Qonun", '18', 'Boshqa shaxsning qonuniy manfaatlarini himoya qilish uchun ishlov berish', self::SHMQ.'#-4397386');
        $shmq28 = self::ref("Shaxsga doir ma'lumotlar to'g'risidagi Qonun", '28', "Rozilik yoki boshqa qonuniy asos bo'lmasa oshkor etish taqiqlanadi", self::SHMQ.'#-4397662');
        $shmq25 = self::ref("Shaxsga doir ma'lumotlar to'g'risidagi Qonun", '25', "Shaxsiy hayot — maxsus ma'lumot; ijro ishi doirasida sudlar orqali", self::SHMQ.'#-4397562');

        return [
            DataRequestType::CourtCase->value => [
                'access' => 'order',
                'items' => ['Sud nomi va sudya', 'Ish holati', 'Majlis sanalari', "Qarorlar ro'yxati"],
                'why' => 'Ish tarafi vakili sifatida advokat ish materiallari bilan tanishish huquqiga ega.',
                'refs' => [$advRights, $advRequest],
            ],
            DataRequestType::TaxInfo->value => [
                'access' => 'order',
                'items' => ['STIR', 'Soliq rejimi va QQS bo\'yicha hisobda turishi', "To'langan soliqlar va soliq qarzi summasi", 'YaTT holati', 'Yuridik shaxslardagi ishtirok'],
                'why' => "Soliq kodeksi 29-moddasining 1–8-bandlarida sanalgan ma'lumotlar soliq sirini tashkil etmaydi.",
                'refs' => [self::ref('Soliq kodeksi', '29', "1–8-bandlar: soliq siri hisoblanmaydigan ma'lumotlar", self::SOLIQ.'#-4675639'), $advRequest],
            ],
            DataRequestType::Salary->value => [
                'access' => 'court',
                'items' => ['Ish beruvchilar', 'Oylik hisoblangan ish haqi', 'Pensiya badallari', "Davr bo'yicha jami"],
                'why' => "Shaxsning daromadlari to'g'risidagi ma'lumot soliq siri — faqat qonunda nazarda tutilgan hollarda, amalda sud qarori bilan.",
                'refs' => [self::ref('Soliq kodeksi', '29', 'Soliq siri va uni oshkor etish taqiqi', self::SOLIQ.'#-4675639'), $shmq28],
            ],
            DataRequestType::Property->value => [
                'access' => 'order',
                'items' => ['Kadastr raqami', 'Manzil, turi, maydoni', 'Ulush', 'Taqiq va garovlar'],
                'why' => "Mulk huquqining davlat ro'yxati — advokat so'rovi orqali olinadi, shaxsga doir ma'lumot sifatida maqsadga bog'langan holda.",
                'refs' => [$advRequest, $shmq18],
            ],
            DataRequestType::Vehicle->value => [
                'access' => 'order',
                'items' => ['Rusumi va yili', 'Davlat raqami', "Ro'yxatga olingan sana", 'Garov'],
                'why' => "Transport vositasining ro'yxati — advokat so'rovi orqali, maqsadga bog'langan holda.",
                'refs' => [$advRequest, $shmq18],
            ],
            DataRequestType::BusinessShares->value => [
                'access' => 'order',
                'items' => ['Yuridik shaxs nomi va STIR', 'Ustav fondidagi ulush', "Ta'sischilik sanasi", 'Faoliyat holati'],
                'why' => "Yuridik shaxs ishtirokchilari to'g'risidagi ma'lumot soliq siri emas (Soliq kodeksi 29-modda, 6-band).",
                'refs' => [self::ref('Soliq kodeksi', '29', "6-band: yuridik shaxs ishtirokchilari to'g'risidagi ma'lumot", self::SOLIQ.'#-4675639'), $advRequest],
            ],
            DataRequestType::BankAccounts->value => [
                'access' => 'court',
                'items' => ['Banklardagi hisobvaraqlar', 'Qoldiq', "Davr bo'yicha aylanma", 'Hisobvaraqqa qo\'yilgan cheklovlar'],
                'why' => "Bank siri faqat qonunda sanalgan subyektlarga, jumladan sudlarga beriladi; advokat so'rovi bu ro'yxatga kirmaydi.",
                'refs' => [self::ref("Bank siri to'g'risidagi Qonun", '10', "Bank siri sudga uning yozma so'rovi asosida, sud ish yurituvidagi ishlar bo'yicha taqdim etiladi", 'https://lex.uz/docs/-41760#-3371575'), $shmq28],
            ],
            DataRequestType::BorderCrossing->value => [
                'access' => 'court',
                'items' => ['Chiqish/kirish sanalari', 'Davlat', "O'tish punkti", 'Hozir chet eldami'],
                'why' => "Chegaradan o'tish ma'lumotlari shaxsiy hayotga taalluqli maxsus ma'lumot — ijro ishi doirasida sud orqali.",
                'refs' => [$shmq25, $shmq28],
            ],
        ];
    }

    /** Things an advocate can NOT obtain through the platform — shown on the map for honesty. */
    public static function outOfScope(): array
    {
        return [
            ['title' => "Tibbiy ma'lumotlar", 'why' => "Salomatlik — maxsus ma'lumot; subyektning yozma roziligi yoki sud/huquqni muhofaza organlari orqali (ShMQ 25-modda)."],
            ['title' => 'Sudlanganlik', 'why' => "Maxsus ma'lumot (ShMQ 25-modda) — platforma orqali so'ralmaydi."],
            ['title' => 'Aloqa va yozishmalar', 'why' => "Telefon so'zlashuvlari va xabarlar — faqat tergov organlari, sud sanksiyasi bilan."],
        ];
    }

    /** @return list<LegalBasisType> */
    public static function acceptedBases(DataRequestType $type): array
    {
        return (self::types()[$type->value]['access'] ?? 'court') === 'order'
            ? [LegalBasisType::CourtDecision, LegalBasisType::AdvocateOrder]
            : [LegalBasisType::CourtDecision];
    }

    /** Types an advocate order may cover. */
    public static function orderTypes(): array
    {
        return array_keys(array_filter(self::types(), fn ($t) => $t['access'] === 'order'));
    }

    public static function domainOf(DataRequestType $type): ?string
    {
        foreach (self::domains() as $d) {
            if (in_array($type->value, $d['types'], true)) {
                return $d['key'];
            }
        }

        return null;
    }

    /** Full map for the UI (/legal-map and request form). */
    public static function toArray(): array
    {
        $types = self::types();

        return [
            'domains' => array_map(fn ($d) => [
                ...$d,
                'types' => array_map(function ($v) use ($types) {
                    $t = DataRequestType::from($v);

                    return [
                        'value' => $v,
                        'label' => $t->label(),
                        'agency' => $t->agency(),
                        ...$types[$v],
                        'accepted_bases' => array_map(fn (LegalBasisType $b) => ['value' => $b->value, 'label' => $b->label()], self::acceptedBases($t)),
                    ];
                }, $d['types']),
            ], self::domains()),
            'out_of_scope' => self::outOfScope(),
            'deadlines' => self::DEADLINES,
            'disclaimer' => self::DISCLAIMER,
        ];
    }
}

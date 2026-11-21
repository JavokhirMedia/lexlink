<?php

namespace App\Services\Requests;

use App\Enums\DataRequestType;
use App\Models\DataRequest;
use App\Models\User;
use App\Services\DataRequestService;
use App\Support\Pinfl;

/** Fallback formal request letter when AI is unavailable. */
class RequestTextTemplate
{
    public function __construct(private DataRequestService $service) {}

    public function render(DataRequest $r, ?User $author = null): string
    {
        $case = $r->legalCase;
        $check = $this->service->checkBasis($r);
        $basis = $check['basis'] ?? $case?->legalBases()->latest('id')->first();
        $advocate = $case?->owner;
        $licence = $advocate?->advocateProfile?->license_number;
        $bureau = $advocate?->advocateProfile?->bureau_name;
        $subject = trim(($r->subject_name ?: 'fuqaro').' (JShShIR: '.($r->subject_pinfl_masked ?: Pinfl::mask($r->subject_pinfl) ?: '—').')');

        $what = match ($r->type) {
            DataRequestType::Salary => sprintf(
                "%s shaxsning %s – %s davri uchun ish haqi (daromadlari), ish beruvchilari hamda pensiya badallari to'g'risidagi ma'lumotlarni",
                $subject, $r->params['period_from'] ?? '…', $r->params['period_to'] ?? '…'
            ),
            DataRequestType::Property => sprintf(
                "%s shaxs nomida ro'yxatdan o'tgan ko'chmas mulk obyektlari, ulushlari va ularga qo'yilgan cheklovlar (taqiqlar)%s to'g'risidagi ma'lumotlarni",
                $subject, ! empty($r->params['cadastral_number']) ? ' (kadastr raqami: '.$r->params['cadastral_number'].')' : ''
            ),
            DataRequestType::Vehicle => "{$subject} shaxs nomida ro'yxatdan o'tgan avtotransport vositalari va ularning garov holati to'g'risidagi ma'lumotlarni",
            DataRequestType::TaxInfo => "{$subject} shaxsning soliq to'lovchi identifikatsiya raqami, qo'llaniladigan soliq rejimi, QQS to'lovchi sifatida hisobda turishi, to'langan soliqlar va soliq qarzi summalari, YaTT holati hamda yuridik shaxslardagi ishtiroki to'g'risidagi (Soliq kodeksi 29-moddasiga ko'ra soliq siri hisoblanmaydigan) ma'lumotlarni",
            DataRequestType::BusinessShares => "{$subject} shaxs ishtirokchi (ta'sischi) bo'lgan yuridik shaxslar, ularning ustav fondidagi ulushi va faoliyat holati to'g'risidagi ma'lumotlarni",
            DataRequestType::BankAccounts => sprintf(
                "%s shaxsning banklardagi hisobvaraqlari, ulardagi qoldiq, %s davrdagi aylanmalar hamda hisobvaraqlarga qo'yilgan cheklovlar to'g'risidagi (bank siri — sud ajrimi asosida) ma'lumotlarni",
                $subject, $this->period($r)
            ),
            DataRequestType::BorderCrossing => sprintf(
                "%s shaxsning %s davrda O'zbekiston Respublikasi davlat chegarasidan o'tganligi (chiqish/kirish sanalari, davlat, o'tish punkti) to'g'risidagi (sud ajrimi asosida) ma'lumotlarni",
                $subject, $this->period($r)
            ),
            DataRequestType::CourtCase => sprintf(
                "%s shaxs ishtirokidagi sud ishi%s holati, sud majlislari va qabul qilingan qarorlar to'g'risidagi ma'lumotlarni",
                $subject, ! empty($r->params['court_case_number']) ? ' (ish raqami: '.$r->params['court_case_number'].')' : ''
            ),
        };

        $basisLine = $basis
            ? ($basis->type->value === 'court_decision'
                ? "{$basis->court_name} tomonidan chiqarilgan {$basis->number}-sonli sud ajrimi".($basis->expires_at ? ' ('.$basis->expires_at->format('d.m.Y').' gacha amal qiladi)' : '')
                : "{$basis->number}-sonli advokat orderi")
            : '[huquqiy asos ko\'rsatilmagan]';

        $lines = [
            $r->type->agency().' rahbariyatiga',
            '',
            "ADVOKAT SO'ROVI",
            '',
            'Hurmatli rahbar!',
            '',
            "\"Advokatura to'g'risida\"gi O'zbekiston Respublikasi Qonunining 6-moddasiga muvofiq, advokat yuridik yordam ko'rsatish maqsadida davlat organlari va boshqa tashkilotlardan zarur ma'lumotlarni so'rash huquqiga ega.",
            '',
            "Yuritilayotgan \"{$case?->title}\"".($case?->court_case_number ? " ({$case->court_case_number}-sonli sud ishi)" : '').' bo\'yicha hamda '.$basisLine.' asosida '.$what.' taqdim etishingizni so\'rayman.',
            '',
            "So'rov maqsadi: ".($r->purpose ?: 'ish bo\'yicha dalillarni to\'plash').'.',
            '',
            "Olingan ma'lumotlar advokat siri sifatida saqlanadi, faqat ko'rsatilgan maqsadda foydalaniladi va uchinchi shaxslarga oshkor etilmaydi (\"Shaxsga doir ma'lumotlar to'g'risida\"gi Qonun).",
            '',
            'Hurmat bilan,',
            'Advokat '.($advocate?->name ?? '________________'),
            $licence ? "Litsenziya № {$licence}".($bureau ? ", {$bureau}" : '') : '',
            'Sana: '.now()->format('d.m.Y'),
        ];

        return trim(implode("\n", $lines));
    }

    private function period(DataRequest $r): string
    {
        $from = $r->params['period_from'] ?? null;
        $to = $r->params['period_to'] ?? null;

        return $from || $to ? ($from ?? '…').' – '.($to ?? '…') : "so'nggi 12 oy";
    }
}

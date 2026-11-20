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
}

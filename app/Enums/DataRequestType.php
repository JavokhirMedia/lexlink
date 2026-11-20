<?php

namespace App\Enums;

enum DataRequestType: string
{
    case CourtCase = 'court_case';
    case Salary = 'salary';
    case Property = 'property';
    case Vehicle = 'vehicle';

    public function label(): string
    {
        return match ($this) {
            self::CourtCase => 'Sud ishi holati',
            self::Salary => 'Ish haqi / daromad',
            self::Property => "Ko'chmas mulk",
            self::Vehicle => 'Avtotransport',
        };
    }

    /** Fictional source agency name shown in UI. */
    public function agency(): string
    {
        return match ($this) {
            self::CourtCase => 'Sud ishlari tizimi',
            self::Salary => "Soliq va pensiya ma'lumotlari",
            self::Property => 'Kadastr',
            self::Vehicle => "YHXB ma'lumotlari",
        };
    }

    /**
     * Which legal basis types are acceptable for this request type.
     * Salary is tax/pension secrecy -> court decision ONLY.
     *
     * @return list<LegalBasisType>
     */
    public function acceptedBases(): array
    {
        return match ($this) {
            self::Salary => [LegalBasisType::CourtDecision],
            default => [LegalBasisType::CourtDecision, LegalBasisType::AdvocateOrder],
        };
    }

    /** Legal secrecy regime, for UI hints / pitch. */
    public function secrecyRegime(): string
    {
        return match ($this) {
            self::Salary => 'Soliq siri — faqat sud qarori yoki ajrimi asosida',
            self::Property => "Shaxsga doir ma'lumot — advokat so'rovi + order yoki sud qarori",
            self::Vehicle => "Shaxsga doir ma'lumot — advokat so'rovi + order yoki sud qarori",
            self::CourtCase => 'Ish tarafi yoki advokat orderi',
        };
    }
}

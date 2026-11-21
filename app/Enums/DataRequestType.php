<?php

namespace App\Enums;

use App\Support\LegalMatrix;

enum DataRequestType: string
{
    case CourtCase = 'court_case';
    case TaxInfo = 'tax_info';
    case Salary = 'salary';
    case Property = 'property';
    case Vehicle = 'vehicle';
    case BusinessShares = 'business_shares';
    case BankAccounts = 'bank_accounts';
    case BorderCrossing = 'border_crossing';

    public function label(): string
    {
        return match ($this) {
            self::CourtCase => 'Sud ishi holati',
            self::TaxInfo => "Soliq ma'lumotlari (ochiq qism)",
            self::Salary => 'Ish haqi / daromad',
            self::Property => "Ko'chmas mulk",
            self::Vehicle => 'Avtotransport',
            self::BusinessShares => 'Yuridik shaxslardagi ulushlar',
            self::BankAccounts => 'Bank hisobvaraqlari',
            self::BorderCrossing => "Chetga chiqish (chegaradan o'tish)",
        };
    }

    /** Fictional source agency name shown in UI. */
    public function agency(): string
    {
        return match ($this) {
            self::CourtCase => 'Sud ishlari tizimi',
            self::TaxInfo => "Soliq ma'lumotlari",
            self::Salary => "Soliq va pensiya ma'lumotlari",
            self::Property => 'Kadastr',
            self::Vehicle => "YHXB ma'lumotlari",
            self::BusinessShares => 'Yuridik shaxslar reyestri',
            self::BankAccounts => 'Banklar (markazlashgan hisobvaraqlar reyestri)',
            self::BorderCrossing => "Chegaradan o'tish ma'lumotlari",
        };
    }

    /** Domain (soha) key from App\Support\LegalMatrix. */
    public function domain(): ?string
    {
        return LegalMatrix::domainOf($this);
    }

    /** 'order' | 'court' — see LegalMatrix. */
    public function access(): string
    {
        return LegalMatrix::types()[$this->value]['access'];
    }

    /**
     * Which legal basis types are acceptable for this request type (derived from LegalMatrix).
     *
     * @return list<LegalBasisType>
     */
    public function acceptedBases(): array
    {
        return LegalMatrix::acceptedBases($this);
    }

    /** Legal secrecy regime, for UI hints. */
    public function secrecyRegime(): string
    {
        return match ($this) {
            self::CourtCase => 'Ochiq (ish tarafi va uning vakili uchun) — advokat orderi yetarli',
            self::TaxInfo => "Soliq siri emas (SK 29-m., 1–8-bandlar) — advokat so'rovi + order",
            self::Salary => 'Soliq siri — faqat sud qarori yoki ajrimi asosida',
            self::Property, self::Vehicle => "Shaxsga doir ma'lumot — advokat so'rovi + order yoki sud qarori",
            self::BusinessShares => "Ishtirokchilar soliq siri emas (SK 29-m., 6-band) — advokat so'rovi + order",
            self::BankAccounts => 'Bank siri — faqat sud qarori yoki ajrimi asosida',
            self::BorderCrossing => "Shaxsiy hayot, maxsus ma'lumot (ShMQ 25-m.) — faqat sud orqali",
        };
    }
}

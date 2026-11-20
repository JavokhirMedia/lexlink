<?php

namespace App\Enums;

enum LegalBasisType: string
{
    case CourtDecision = 'court_decision';
    case AdvocateOrder = 'advocate_order';

    public function label(): string
    {
        return match ($this) {
            self::CourtDecision => 'Sud qarori / ajrimi',
            self::AdvocateOrder => 'Advokat orderi',
        };
    }
}

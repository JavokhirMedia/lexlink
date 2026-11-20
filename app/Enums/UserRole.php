<?php

namespace App\Enums;

enum UserRole: string
{
    case Advocate = 'advocate';
    case Lawyer = 'lawyer';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Advocate => 'Advokat',
            self::Lawyer => 'Yurist',
            self::Admin => 'Administrator',
        };
    }
}

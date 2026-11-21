<?php

namespace App\Enums;

enum UserRole: string
{
    case Advocate = 'advocate';
    case Lawyer = 'lawyer';
    case Admin = 'admin';
    case Client = 'client'; // ishonch bildiruvchi — only via an advocate's client access link

    public function label(): string
    {
        return match ($this) {
            self::Advocate => 'Advokat',
            self::Lawyer => 'Yurist',
            self::Admin => 'Administrator',
            self::Client => 'Mijoz',
        };
    }
}

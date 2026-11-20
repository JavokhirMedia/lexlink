<?php

namespace App\Enums;

/**
 * draft -> awaiting_signature -> submitted -> processing -> completed | rejected | failed
 */
enum DataRequestStatus: string
{
    case Draft = 'draft';
    case AwaitingSignature = 'awaiting_signature';
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Loyiha',
            self::AwaitingSignature => 'Imzo kutilmoqda',
            self::Submitted => 'Yuborildi',
            self::Processing => 'Jarayonda',
            self::Completed => 'Tayyor',
            self::Rejected => 'Rad etildi',
            self::Failed => 'Texnik xato',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Failed], true);
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::AwaitingSignature],
            self::AwaitingSignature => [self::Draft, self::Submitted],
            self::Submitted => [self::Processing, self::Rejected, self::Failed],
            self::Processing => [self::Completed, self::Rejected, self::Failed],
            default => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }
}

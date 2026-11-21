<?php

namespace App\Models;

use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Support\Pinfl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalBasis extends Model
{
    protected $table = 'legal_bases';

    protected $fillable = ['case_id', 'created_by', 'type', 'number', 'issued_at', 'expires_at', 'subject_pinfl', 'subject_name', 'purpose', 'court_name', 'allowed_types', 'file_path', 'verification_status', 'verification_payload', 'verified_at'];

    protected $hidden = ['subject_pinfl', 'subject_pinfl_hash', 'verification_payload'];

    protected function casts(): array
    {
        return [
            'type' => LegalBasisType::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'subject_pinfl' => 'encrypted',
            'allowed_types' => 'array',
            'verification_payload' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LegalBasis $b) {
            if ($b->isDirty('subject_pinfl')) {
                $b->subject_pinfl_hash = $b->subject_pinfl ? Pinfl::hash($b->subject_pinfl) : null;
                $b->subject_pinfl_masked = Pinfl::mask($b->subject_pinfl);
            }
        });
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function allows(DataRequestType $type): bool
    {
        return in_array($type->value, $this->allowed_types ?? [], true);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function dataRequests(): HasMany
    {
        return $this->hasMany(DataRequest::class);
    }
}

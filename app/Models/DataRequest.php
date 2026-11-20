<?php

namespace App\Models;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Support\Pinfl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Status changes MUST go through App\Services\DataRequestService (state machine + events + audit).
 */
class DataRequest extends Model
{
    protected $fillable = ['case_id', 'legal_basis_id', 'type', 'subject_pinfl', 'subject_name', 'params', 'purpose', 'request_text', 'status', 'created_by', 'signed_by', 'signed_at', 'signature', 'gov_request_id', 'cost', 'charged_from_subscription', 'submitted_at', 'completed_at', 'rejection_reason', 'failure_reason'];

    protected $hidden = ['subject_pinfl', 'subject_pinfl_hash'];

    protected function casts(): array
    {
        return [
            'type' => DataRequestType::class,
            'status' => DataRequestStatus::class,
            'subject_pinfl' => 'encrypted',
            'params' => 'array',
            'signed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'charged_from_subscription' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (DataRequest $r) {
            if ($r->isDirty('subject_pinfl')) {
                $r->subject_pinfl_hash = $r->subject_pinfl ? Pinfl::hash($r->subject_pinfl) : null;
                $r->subject_pinfl_masked = Pinfl::mask($r->subject_pinfl);
            }
        });
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function legalBasis(): BelongsTo
    {
        return $this->belongsTo(LegalBasis::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }

    public function response(): HasOne
    {
        return $this->hasOne(RequestResponse::class);
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class);
    }
}

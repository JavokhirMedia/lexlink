<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Client (ishonch bildiruvchi) read-only access to a case, based on the advocate order.
 * Access is granted only to the OneID user whose PINFL hash equals client_pinfl_hash.
 */
class CaseClientLink extends Model
{
    protected $fillable = ['case_id', 'order_number', 'client_pinfl_hash', 'client_name', 'token', 'created_by', 'client_user_id', 'granted_at', 'last_viewed_at', 'revoked_at'];

    protected $hidden = ['client_pinfl_hash', 'token'];

    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'last_viewed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }
}

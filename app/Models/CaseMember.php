<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseMember extends Model
{
    protected $fillable = ['case_id', 'user_id', 'role', 'can_view_responses', 'secrecy_declared_at'];

    protected function casts(): array
    {
        return ['can_view_responses' => 'boolean', 'secrecy_declared_at' => 'datetime'];
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

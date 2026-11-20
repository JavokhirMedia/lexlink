<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvocateLawyerLink extends Model
{
    protected $fillable = ['advocate_id', 'lawyer_id', 'invite_contact', 'token', 'status', 'invited_at', 'accepted_at'];

    protected function casts(): array
    {
        return ['invited_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    public function advocate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advocate_id');
    }

    public function lawyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lawyer_id');
    }
}

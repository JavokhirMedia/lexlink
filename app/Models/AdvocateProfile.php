<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvocateProfile extends Model
{
    protected $fillable = ['user_id', 'license_number', 'license_status', 'bureau_name', 'verified_at', 'last_checked_at'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'last_checked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

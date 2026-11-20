<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestResponse extends Model
{
    protected $fillable = ['data_request_id', 'summary', 'payload', 'pdf_path', 'gov_signature', 'received_at', 'expires_at', 'purged_at'];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'payload' => 'encrypted:array',
            'received_at' => 'datetime',
            'expires_at' => 'datetime',
            'purged_at' => 'datetime',
        ];
    }

    public function isPurged(): bool
    {
        return $this->purged_at !== null;
    }

    public function dataRequest(): BelongsTo
    {
        return $this->belongsTo(DataRequest::class);
    }
}

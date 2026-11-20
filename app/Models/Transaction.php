<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = ['wallet_id', 'type', 'amount', 'balance_after', 'provider', 'data_request_id', 'status', 'external_id', 'description'];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function dataRequest(): BelongsTo
    {
        return $this->belongsTo(DataRequest::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['code', 'name', 'audience', 'price', 'included_requests', 'extra_price', 'included_ai', 'features'];

    protected function casts(): array
    {
        return ['features' => 'array'];
    }
}

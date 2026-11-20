<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LawDocument extends Model
{
    protected $fillable = ['slug', 'title', 'short_title', 'lex_id', 'lex_url', 'adopted_at'];

    protected function casts(): array
    {
        return ['adopted_at' => 'date'];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(LawChunk::class);
    }
}

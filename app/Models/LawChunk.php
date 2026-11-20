<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LawChunk extends Model
{
    protected $fillable = ['law_document_id', 'article_number', 'article_title', 'anchor', 'chapter', 'part', 'content', 'content_hash', 'embedding', 'token_count'];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return ['embedding' => 'array'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(LawDocument::class, 'law_document_id');
    }
}

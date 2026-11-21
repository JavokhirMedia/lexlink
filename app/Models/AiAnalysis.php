<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysis extends Model
{
    protected $fillable = ['data_request_id', 'case_id', 'created_by', 'kind', 'status', 'model', 'prompt_version', 'output', 'sent_payload', 'anonymization_report', 'structured', 'citations', 'tokens', 'cost'];

    protected function casts(): array
    {
        return ['structured' => 'array', 'citations' => 'array', 'anonymization_report' => 'array'];
    }

    public function dataRequest(): BelongsTo
    {
        return $this->belongsTo(DataRequest::class);
    }

    public function legalCase(): BelongsTo
    {
        return $this->belongsTo(LegalCase::class, 'case_id');
    }
}

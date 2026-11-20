<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockGovRequest extends Model
{
    protected $table = 'mock_gov_requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['legal_basis' => 'array', 'period' => 'array', 'params' => 'array', 'result' => 'array', 'ready_at' => 'datetime'];
    }
}

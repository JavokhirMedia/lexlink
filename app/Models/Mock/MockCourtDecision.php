<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockCourtDecision extends Model
{
    protected $table = 'mock_court_decisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['allowed_types' => 'array', 'issued_at' => 'date', 'expires_at' => 'date'];
    }
}

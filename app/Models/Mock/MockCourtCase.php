<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockCourtCase extends Model
{
    protected $table = 'mock_court_cases';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['parties' => 'array', 'hearings' => 'array', 'decisions' => 'array'];
    }
}

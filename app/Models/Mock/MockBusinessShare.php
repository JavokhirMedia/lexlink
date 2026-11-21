<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockBusinessShare extends Model
{
    protected $table = 'mock_business_shares';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['share_percent' => 'float', 'charter_capital' => 'integer', 'since' => 'date'];
    }
}

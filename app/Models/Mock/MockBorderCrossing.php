<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockBorderCrossing extends Model
{
    protected $table = 'mock_border_crossings';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['crossed_at' => 'datetime'];
    }
}

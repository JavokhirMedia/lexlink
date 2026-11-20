<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockOneidCode extends Model
{
    protected $table = 'mock_oneid_codes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }
}

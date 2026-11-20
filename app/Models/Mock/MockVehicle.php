<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockVehicle extends Model
{
    protected $table = 'mock_vehicles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['year' => 'integer', 'registered_at' => 'date', 'pledge' => 'array'];
    }
}

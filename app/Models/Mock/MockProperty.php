<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockProperty extends Model
{
    protected $table = 'mock_properties';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['area' => 'float', 'registered_at' => 'date', 'encumbrances' => 'array'];
    }
}

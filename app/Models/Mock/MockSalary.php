<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockSalary extends Model
{
    protected $table = 'mock_salaries';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'pension_contribution' => 'integer'];
    }
}

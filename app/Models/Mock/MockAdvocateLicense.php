<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockAdvocateLicense extends Model
{
    protected $table = 'mock_advocate_licenses';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'revoked_at' => 'date'];
    }
}

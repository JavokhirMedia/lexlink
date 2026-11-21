<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockBankAccount extends Model
{
    protected $table = 'mock_bank_accounts';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['balance' => 'integer', 'turnover_in' => 'integer', 'turnover_out' => 'integer', 'opened_at' => 'date', 'restrictions' => 'array'];
    }
}

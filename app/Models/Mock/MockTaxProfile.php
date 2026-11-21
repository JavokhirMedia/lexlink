<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional government registry row (mock-gov only — never read from the main app). */
class MockTaxProfile extends Model
{
    protected $table = 'mock_tax_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['vat_payer' => 'boolean', 'is_individual_entrepreneur' => 'boolean', 'tax_debt' => 'integer', 'taxes_paid_last_year' => 'integer', 'registered_at' => 'date'];
    }
}

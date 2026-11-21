<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional "Advokatlar palatasi — elektron orderlar reyestri" row (mock-gov only). */
class MockAdvocateOrder extends Model
{
    protected $table = 'mock_advocate_orders';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['issued_at' => 'date'];
    }
}

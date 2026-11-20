<?php

namespace App\Models\Mock;

use Illuminate\Database\Eloquent\Model;

/** Fictional citizen registry row (mock-gov only — never read from the main app). */
class MockPerson extends Model
{
    protected $table = 'mock_persons';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['birth_date' => 'date'];
    }

    /** Stable OneID subject identifier for a PINFL (what /oneid/userinfo returns as `sub`). */
    public static function oneIdSubFor(string $pinfl): string
    {
        return 'oneid-'.substr(hash('sha256', 'mock-oneid|'.$pinfl), 0, 24);
    }

    public function fullName(): string
    {
        return trim("{$this->last_name} {$this->first_name} {$this->middle_name}");
    }

    /** "Karimov A. B." */
    public function shortName(): string
    {
        $initials = collect([$this->first_name, $this->middle_name])
            ->filter()
            ->map(fn (string $n) => mb_substr($n, 0, 1).'.')
            ->implode(' ');

        return trim("{$this->last_name} {$initials}");
    }
}

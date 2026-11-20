<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\AdvocateProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Users sign in via OneID only (no passwords). Default state = onboarded lawyer.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $pinfl = (string) fake()->unique()->numerify('3#############');

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => null,
            'oneid_sub' => 'oneid-'.Str::lower(Str::random(16)),
            'pinfl' => $pinfl,
            'phone' => '+99890'.fake()->numerify('#######'),
            'role' => UserRole::Lawyer,
            'remember_token' => Str::random(10),
        ];
    }

    /** Fresh OneID user who has not finished onboarding yet. */
    public function unonboarded(): static
    {
        return $this->state(fn () => ['role' => null]);
    }

    public function lawyer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Lawyer]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    /** Advocate with an AdvocateProfile in the given licence status (active|revoked|suspended). */
    public function advocate(string $licenseStatus = 'active', ?string $licenseNumber = null): static
    {
        return $this->state(fn () => ['role' => UserRole::Advocate])
            ->afterCreating(function (User $user) use ($licenseStatus, $licenseNumber) {
                AdvocateProfile::create([
                    'user_id' => $user->id,
                    'license_number' => $licenseNumber ?? 'ADV-'.fake()->unique()->numerify('#####'),
                    'license_status' => $licenseStatus,
                    'bureau_name' => 'Demo advokatlik byurosi',
                    'verified_at' => now(),
                    'last_checked_at' => now(),
                ]);
            });
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}

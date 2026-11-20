<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Support\Pinfl;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $email
 * @property string|null $oneid_sub
 * @property string|null $pinfl
 * @property string|null $pinfl_hash
 * @property string|null $phone
 * @property UserRole|null $role
 */
#[Fillable(['name', 'email', 'password', 'oneid_sub', 'pinfl', 'pinfl_hash', 'phone', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'pinfl', 'pinfl_hash'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'pinfl' => 'encrypted',
            'role' => UserRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('pinfl')) {
                $user->pinfl_hash = $user->pinfl ? Pinfl::hash($user->pinfl) : null;
            }
        });
    }

    public function isAdvocate(): bool
    {
        return $this->role === UserRole::Advocate;
    }

    public function isLawyer(): bool
    {
        return $this->role === UserRole::Lawyer;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Licensed advocate whose licence is currently active (may sign & submit). */
    public function hasActiveLicense(): bool
    {
        return $this->isAdvocate() && $this->advocateProfile?->license_status === 'active';
    }

    public function advocateProfile(): HasOne
    {
        return $this->hasOne(AdvocateProfile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('period_end', '>=', now()->toDateString())->latestOfMany();
    }

    public function ownedCases(): HasMany
    {
        return $this->hasMany(LegalCase::class, 'owner_id');
    }

    public function cases(): BelongsToMany
    {
        return $this->belongsToMany(LegalCase::class, 'case_members', 'user_id', 'case_id')
            ->withPivot(['role', 'can_view_responses', 'secrecy_declared_at'])->withTimestamps();
    }

    /** Lawyers linked to this advocate. */
    public function lawyerLinks(): HasMany
    {
        return $this->hasMany(AdvocateLawyerLink::class, 'advocate_id');
    }

    /** Advocates this lawyer works with. */
    public function advocateLinks(): HasMany
    {
        return $this->hasMany(AdvocateLawyerLink::class, 'lawyer_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }
}

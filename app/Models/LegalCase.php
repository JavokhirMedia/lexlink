<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** "case" is a PHP keyword, hence LegalCase -> table `cases`. */
class LegalCase extends Model
{
    protected $table = 'cases';

    protected $fillable = ['owner_id', 'title', 'court_case_number', 'client_name', 'category', 'description', 'parties', 'status'];

    protected function casts(): array
    {
        return ['parties' => 'array'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'case_members', 'case_id', 'user_id')
            ->withPivot(['role', 'can_view_responses', 'secrecy_declared_at'])->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CaseMember::class, 'case_id');
    }

    public function legalBases(): HasMany
    {
        return $this->hasMany(LegalBasis::class, 'case_id');
    }

    public function dataRequests(): HasMany
    {
        return $this->hasMany(DataRequest::class, 'case_id');
    }

    public function aiAnalyses(): HasMany
    {
        return $this->hasMany(AiAnalysis::class, 'case_id');
    }

    public function membershipOf(User $user): ?CaseMember
    {
        return $this->memberships()->where('user_id', $user->id)->first();
    }
}

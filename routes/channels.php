<?php

use App\Models\LegalCase;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);

Broadcast::channel('case.{caseId}', function ($user, $caseId) {
    $case = LegalCase::find($caseId);

    return $case && $case->memberships()->where('user_id', $user->id)->exists();
});

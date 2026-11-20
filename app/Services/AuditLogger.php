<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit trail. Every view/download/sign/submit of PII goes through here.
 * Never put raw PINFL or payload data into $meta — use masked values.
 */
class AuditLogger
{
    public function log(AuditAction $action, ?Model $subject = null, array $meta = [], ?int $caseId = null, ?User $user = null): AuditLog
    {
        $request = request();
        $user ??= auth()->user();

        if ($caseId === null && $subject !== null) {
            $caseId = $subject->getAttribute('case_id') ?? ($subject->getTable() === 'cases' ? $subject->getKey() : null);
        }

        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'case_id' => $caseId,
            'meta' => $meta ?: null,
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}

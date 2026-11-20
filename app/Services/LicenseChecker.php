<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;
use App\Support\Pinfl;
use Illuminate\Support\Facades\Log;

/**
 * Re-checks an advocate's licence against the advocate registry (Advokatlar palatasi reestri).
 *
 * Rule: only an advocate with an ACTIVE licence may sign and submit requests; a revoked or
 * suspended licence removes signing rights immediately. Call refresh() right before signing.
 */
class LicenseChecker
{
    public function __construct(
        private readonly GovDataProvider $gov,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Refresh license_status / last_checked_at of the user's AdvocateProfile from the registry.
     *
     * Returns true only if the user is an advocate whose licence is active right now.
     * Fails closed: if the registry is unreachable, returns false and leaves the stored status untouched.
     * Logs AuditAction::LicenseRevoked when the status changes from active to anything else.
     */
    public function refresh(User $user): bool
    {
        $profile = $user->advocateProfile;

        if (! $user->isAdvocate() || $profile === null) {
            return false;
        }

        try {
            $record = $this->gov->advocateLicense($profile->license_number);
        } catch (GovApiException $e) {
            Log::warning('Licence re-check failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return false;
        }

        [$status, $reason] = $this->statusFrom($record, $user);
        $previous = $profile->license_status;

        $profile->forceFill([
            'license_status' => $status,
            'bureau_name' => $record['bureau_name'] ?? $profile->bureau_name,
            'last_checked_at' => now(),
        ])->save();

        if ($previous !== $status) {
            if ($status === 'active') {
                $this->audit->log(AuditAction::LicenseVerified, $profile, [
                    'license_number' => $profile->license_number,
                    'previous_status' => $previous,
                    'status' => $status,
                ], user: $user);
            } elseif ($previous === 'active') {
                $this->audit->log(AuditAction::LicenseRevoked, $profile, array_filter([
                    'license_number' => $profile->license_number,
                    'previous_status' => $previous,
                    'status' => $status,
                    'reason' => $reason,
                ]), user: $user);
            }
        }

        $user->setRelation('advocateProfile', $profile);

        return $status === 'active';
    }

    /**
     * @param  array<string, mixed>|null  $record
     * @return array{0: string, 1: string|null}
     */
    private function statusFrom(?array $record, User $user): array
    {
        if ($record === null) {
            return ['revoked', 'not_found'];
        }

        if (! self::pinflMatches($record, $user)) {
            return ['revoked', 'pinfl_mismatch'];
        }

        $status = (string) ($record['status'] ?? 'revoked');

        return [in_array($status, ['active', 'revoked', 'suspended'], true) ? $status : 'revoked', null];
    }

    /** Registry PINFL must equal the user's OneID PINFL. */
    public static function pinflMatches(array $record, User $user): bool
    {
        $registryPinfl = (string) ($record['pinfl'] ?? '');

        return $registryPinfl !== '' && $user->pinfl !== null
            && hash_equals(Pinfl::hash($user->pinfl), Pinfl::hash($registryPinfl));
    }
}

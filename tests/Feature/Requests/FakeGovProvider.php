<?php

namespace Tests\Feature\Requests;

use App\Models\AdvocateProfile;
use App\Services\Gov\Dto\GovSubmission;
use App\Services\Gov\GovApiException;
use App\Services\Gov\GovDataProvider;

/** In-memory agency: ready 3 s after submit; optional rejection / transport failure. */
class FakeGovProvider implements GovDataProvider
{
    /** @var array<string, array{submission: GovSubmission, ready_at: \DateTimeInterface}> */
    public array $requests = [];

    public int $submitCalls = 0;

    public bool $failSubmit = false;

    public ?string $rejectWith = null;

    public bool $registryDown = false;

    public function oneIdUser(string $code, string $redirectUri): array
    {
        return [];
    }

    public function advocateLicense(string $licenseNumber): ?array
    {
        if ($this->registryDown) {
            throw new \App\Services\Gov\GovApiException('registry unavailable');
        }
        $profile = AdvocateProfile::with('user')->where('license_number', $licenseNumber)->first();

        return $profile ? [
            'license_number' => $licenseNumber,
            'full_name' => $profile->user->name,
            'pinfl' => $profile->user->pinfl,
            'status' => $profile->license_status,
            'bureau_name' => $profile->bureau_name,
            'issued_at' => '2020-01-01',
        ] : null;
    }

    public function courtDecision(string $number): ?array
    {
        return null;
    }

    public function courtCase(string $number): ?array
    {
        return null;
    }

    public function submit(GovSubmission $submission): array
    {
        $this->submitCalls++;
        if ($this->failSubmit) {
            throw new GovApiException('connection refused');
        }
        $id = sprintf('GOV-2026-%06d', count($this->requests) + 1);
        $this->requests[$id] = ['submission' => $submission, 'ready_at' => now()->addSeconds(3)];

        return ['request_id' => $id, 'status' => 'accepted'];
    }

    public function status(string $requestId): array
    {
        $r = $this->requests[$requestId];
        if (now()->lt($r['ready_at'])) {
            return ['request_id' => $requestId, 'status' => 'processing'];
        }
        if ($this->rejectWith) {
            return ['request_id' => $requestId, 'status' => 'rejected', 'rejection_reason' => $this->rejectWith];
        }

        return ['request_id' => $requestId, 'status' => 'completed'];
    }

    public function result(string $requestId): array
    {
        $type = $this->requests[$requestId]['submission']->type;

        $data = match ($type) {
            'salary' => [
                'employers' => [['name' => '"Demo Qurilish" MChJ', 'tin' => '301111111'], ['name' => '"Namuna Savdo" MChJ', 'tin' => '302222222']],
                'months' => [
                    ['period' => '2026-05', 'employer' => '"Demo Qurilish" MChJ', 'amount' => 4_000_000, 'pension_contribution' => 40_000],
                    ['period' => '2026-06', 'employer' => '"Demo Qurilish" MChJ', 'amount' => 4_000_000, 'pension_contribution' => 40_000],
                    ['period' => '2026-06', 'employer' => '"Namuna Savdo" MChJ', 'amount' => 1_000_000, 'pension_contribution' => 10_000],
                    ['period' => '2026-07', 'employer' => '"Demo Qurilish" MChJ', 'amount' => 3_000_000, 'pension_contribution' => 30_000],
                ],
                'total' => 12_000_000,
                'average' => 4_000_000,
                'period' => ['from' => '2026-05', 'to' => '2026-07'],
            ],
            'property' => ['objects' => [['cadastral_number' => '10:01:02:03:0004', 'type' => 'Kvartira', 'address' => 'Toshkent sh.', 'area' => 64.5, 'share' => '1/1', 'registered_at' => '2019-04-01', 'encumbrances' => []]]],
            'vehicle' => ['vehicles' => []],
            default => ['case_number' => '2-1001-2611/0457'],
        };

        return [
            'request_id' => $requestId,
            'status' => 'completed',
            'type' => $type,
            'subject' => ['pinfl' => '3**********12', 'full_name' => 'Demo F.'],
            'data' => $data,
            'issued_at' => now()->toIso8601String(),
            'signature' => hash('sha256', json_encode($data)),
        ];
    }

    /** @var array<string, array<string, mixed>> */
    public array $orders = [];

    public function advocateOrder(string $number): ?array
    {
        return $this->orders[$number] ?? null;
    }

    public function issueAdvocateOrder(array $data): array
    {
        throw new GovApiException('not faked');
    }
}

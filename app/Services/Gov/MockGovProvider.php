<?php

namespace App\Services\Gov;

use App\Services\Gov\Dto\GovSubmission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * GovDataProvider over HTTP against the fictional agency at config('lexlink.mock_gov.base_url').
 *
 * NOTE: `php artisan serve` is single-threaded, so HTTP-to-self deadlocks there — run it with
 * PHP_CLI_SERVER_WORKERS=4 or point MOCK_GOV_BASE_URL at a second server. nginx + php-fpm is fine.
 */
class MockGovProvider implements GovDataProvider
{
    public function oneIdUser(string $code, string $redirectUri): array
    {
        $token = $this->send(fn () => $this->client(false)->asForm()->post('oneid/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => config('lexlink.mock_gov.oneid_client_id'),
            'client_secret' => config('lexlink.mock_gov.oneid_client_secret'),
        ]));
        $this->ensureOk($token, 'OneID token');

        $accessToken = $token->json('access_token');
        if (! is_string($accessToken) || $accessToken === '') {
            throw new GovApiException('OneID: access_token qaytmadi.');
        }

        $user = $this->send(fn () => $this->client(false)->withToken($accessToken)->get('oneid/userinfo'));
        $this->ensureOk($user, 'OneID userinfo');

        return $user->json();
    }

    public function advocateLicense(string $licenseNumber): ?array
    {
        return $this->lookup('advocates/'.rawurlencode(trim($licenseNumber)));
    }

    public function courtDecision(string $number): ?array
    {
        return $this->lookup('court/decisions/'.$this->pathSegment($number));
    }

    public function courtCase(string $number): ?array
    {
        return $this->lookup('court/cases/'.$this->pathSegment($number));
    }

    public function submit(GovSubmission $submission): array
    {
        $response = $this->send(fn () => $this->client()->post('requests', $submission->toArray()));
        $this->ensureOk($response, "So'rov yuborish");

        return $response->json();
    }

    public function status(string $requestId): array
    {
        $json = $this->fetchRequest($requestId);

        return array_filter([
            'request_id' => $json['request_id'] ?? $requestId,
            'status' => $json['status'] ?? 'processing',
            'rejection_reason' => $json['rejection_reason'] ?? null,
        ], fn ($v) => $v !== null);
    }

    public function result(string $requestId): array
    {
        $json = $this->fetchRequest($requestId);

        if (($json['status'] ?? null) !== 'completed') {
            throw new GovApiException("So'rov hali yakunlanmagan (holat: ".($json['status'] ?? 'unknown').').');
        }

        return $json;
    }

    private function fetchRequest(string $requestId): array
    {
        $response = $this->send(fn () => $this->client()->get('requests/'.rawurlencode($requestId)));
        $this->ensureOk($response, "So'rov holati");

        return $response->json();
    }

    private function lookup(string $path): ?array
    {
        $response = $this->send(fn () => $this->client()->get($path));

        if ($response->status() === 404) {
            return null;
        }
        $this->ensureOk($response, 'Reestr');

        return $response->json();
    }

    /** Numbers like "2-1001-2611/0457" contain "/", which the agency route accepts unencoded. */
    private function pathSegment(string $value): string
    {
        return implode('/', array_map('rawurlencode', explode('/', trim($value))));
    }

    private function client(bool $withApiKey = true): PendingRequest
    {
        $client = Http::baseUrl(rtrim((string) config('lexlink.mock_gov.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10);

        return $withApiKey ? $client->withHeaders(['X-Api-Key' => (string) config('lexlink.mock_gov.api_key')]) : $client;
    }

    private function send(callable $call): Response
    {
        try {
            return $call();
        } catch (ConnectionException $e) {
            throw new GovApiException('Davlat tizimi bilan aloqa yo\'q: '.$e->getMessage(), 0, $e);
        }
    }

    private function ensureOk(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message') ?? $response->json('error_description') ?? $response->json('error');

        throw new GovApiException(sprintf('%s: HTTP %d%s', $what, $response->status(), $message ? " — {$message}" : ''));
    }
}

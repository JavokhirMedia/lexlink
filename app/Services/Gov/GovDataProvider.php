<?php

namespace App\Services\Gov;

use App\Services\Gov\Dto\GovSubmission;

/**
 * Single seam to "government" systems. Today: MockGovProvider (HTTP to /mock-gov/v1).
 * Going live = new implementation (e.g. MIP/EGOV adapter); nothing else changes.
 *
 * All methods return plain arrays decoded from the agency JSON (see mock API docs in
 * docs/mock-gov-api.md). Methods throw App\Services\Gov\GovApiException on transport errors.
 */
interface GovDataProvider
{
    /** OneID: exchange auth code for user info: {sub, pinfl, full_name, first_name, last_name, middle_name, birth_date, phone} */
    public function oneIdUser(string $code, string $redirectUri): array;

    /** Advocate registry: {license_number, full_name, pinfl, status: active|revoked|suspended, bureau_name, issued_at} or null if not found */
    public function advocateLicense(string $licenseNumber): ?array;

    /** Court decision registry: {number, case_number, court, judge, decision_type, subject:{pinfl, full_name}, allowed_types[], issued_at, expires_at, summary} or null */
    public function courtDecision(string $number): ?array;

    /** Court case status: {case_number, court, judge, category, status, parties[], hearings[], decisions[]} or null */
    public function courtCase(string $number): ?array;

    /** Submit a data request; returns {request_id, status: "accepted"} */
    public function submit(GovSubmission $submission): array;

    /** Poll status: {request_id, status: accepted|processing|completed|rejected, rejection_reason?} */
    public function status(string $requestId): array;

    /** Full result (only when completed): {request_id, status, type, subject:{pinfl(masked), full_name}, data:{...}, issued_at, signature} */
    public function result(string $requestId): array;
}

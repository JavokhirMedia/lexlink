<?php

namespace App\Services\Ai\Anonymization;

use RuntimeException;

/** Thrown by the gateway when the leak guard finds personal data in an outgoing text. Carries counts only. */
final class PiiLeakBlocked extends RuntimeException
{
    /**
     * @param  array<string, int>  $categories
     * @param  array<string, mixed>  $report
     */
    public function __construct(public readonly array $categories, public readonly array $report)
    {
        parent::__construct('AI request blocked: personal data detected ('.implode(', ', array_keys($categories)).')');
    }
}

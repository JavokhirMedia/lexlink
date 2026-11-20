<?php

namespace App\Services\Gov\Dto;

final readonly class GovSubmission
{
    /**
     * @param  string  $type  court_case|salary|property|vehicle
     * @param  array{type: string, number: string}  $legalBasis
     * @param  array{from?: string, to?: string}|null  $period  YYYY-MM
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public string $type,
        public string $subjectPinfl,
        public array $legalBasis,
        public ?array $period,
        public string $requesterLicense,
        public array $params = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'subject_pinfl' => $this->subjectPinfl,
            'legal_basis' => $this->legalBasis,
            'period' => $this->period,
            'requester_license' => $this->requesterLicense,
            'params' => $this->params,
        ];
    }
}

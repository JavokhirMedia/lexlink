<?php

namespace App\Http\Controllers\Cases;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Services\LegalBasisVerifier;
use App\Support\RequestDeadline;

/** Serializers matching resources/js/types/lexlink.ts (snake_case). */
final class CasePresenter
{
    public const CATEGORIES = [
        'aliment' => 'Aliment',
        'mehnat' => 'Mehnat nizosi',
        'mulk' => 'Mulk nizosi',
        'fuqarolik' => 'Fuqarolik ishi',
        'boshqa' => 'Boshqa',
    ];

    public static function legalCase(LegalCase $c): array
    {
        return [
            'id' => $c->id,
            'title' => $c->title,
            'court_case_number' => $c->court_case_number,
            'client_name' => $c->client_name,
            'category' => $c->category,
            'category_label' => self::CATEGORIES[$c->category] ?? null,
            'description' => $c->description,
            'parties' => $c->parties ?? [],
            'status' => $c->status,
            'owner' => ['id' => $c->owner?->id, 'name' => $c->owner?->name],
            'created_at' => $c->created_at?->toIso8601String(),
        ];
    }

    public static function basis(LegalBasis $b): array
    {
        $allowed = $b->allowed_types ?? [];
        $possible = $b->type === LegalBasisType::AdvocateOrder
            ? LegalBasisVerifier::advocateOrderTypes()
            : array_map(fn (DataRequestType $t) => $t->value, DataRequestType::cases());

        return [
            'id' => $b->id,
            'type' => $b->type->value,
            'type_label' => $b->type->label(),
            'number' => $b->number,
            'issued_at' => $b->issued_at?->toDateString(),
            'expires_at' => $b->expires_at?->toDateString(),
            'subject_pinfl_masked' => $b->subject_pinfl_masked,
            'subject_name' => $b->subject_name,
            'court_name' => $b->court_name,
            'allowed_types' => $allowed,
            'verification_status' => $b->verification_status,
            'verified_at' => $b->verified_at?->toIso8601String(),
            // extensions
            'allowed_type_labels' => array_values(array_map(fn ($t) => DataRequestType::from($t)->label(), $allowed)),
            'not_covered_types' => array_values(array_map(
                fn ($t) => ['value' => $t, 'label' => DataRequestType::from($t)->label()],
                array_values(array_diff(array_map(fn (DataRequestType $t) => $t->value, DataRequestType::cases()), $allowed)),
            )),
            'possible_types' => $possible,
            'is_expired' => $b->isExpired(),
            'verification_error' => $b->verification_payload['error'] ?? null,
            'purpose' => $b->purpose,
            'order' => $b->type === LegalBasisType::AdvocateOrder && isset($b->verification_payload['order']) ? [
                'client_name' => $b->verification_payload['order']['client']['full_name'] ?? null,
                'client_pinfl_masked' => $b->verification_payload['order']['client']['pinfl_masked'] ?? null,
                'matter' => $b->verification_payload['order']['matter'] ?? null,
                'license_number' => $b->verification_payload['order']['license_number'] ?? null,
                'advocate_name' => $b->verification_payload['order']['advocate_name'] ?? null,
                'status' => $b->verification_payload['order']['status'] ?? null,
                'client_known' => ! empty($b->verification_payload['client_pinfl_hash']),
            ] : null,
            'has_file' => $b->file_path !== null,
            'created_by' => $b->creator ? ['id' => $b->creator->id, 'name' => $b->creator->name] : null,
        ];
    }

    public static function request(DataRequest $r): array
    {
        return [
            'id' => $r->id,
            'case_id' => $r->case_id,
            'type' => $r->type->value,
            'type_label' => $r->type->label(),
            'agency' => $r->type->agency(),
            'status' => $r->status->value,
            'status_label' => $r->status->label(),
            'subject_pinfl_masked' => $r->subject_pinfl_masked,
            'subject_name' => $r->subject_name,
            'params' => $r->params,
            'purpose' => $r->purpose,
            'request_text' => $r->request_text,
            'cost' => (int) $r->cost,
            'gov_request_id' => $r->gov_request_id,
            'rejection_reason' => $r->rejection_reason,
            'signed_at' => $r->signed_at?->toIso8601String(),
            'submitted_at' => $r->submitted_at?->toIso8601String(),
            'due_at' => $r->due_at?->toIso8601String(),
            'deadline_basis' => $r->deadline_basis,
            'deadline_label' => RequestDeadline::label($r->deadline_basis),
            'answered_on_time' => $r->due_at && $r->completed_at && $r->status === DataRequestStatus::Completed ? $r->completed_at->lte($r->due_at) : null,
            'completed_at' => $r->completed_at?->toIso8601String(),
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    public static function member(CaseMember $m): array
    {
        return [
            'id' => $m->id,
            'user' => [
                'id' => $m->user->id,
                'name' => $m->user->name,
                'role' => $m->user->role?->value,
                'role_label' => $m->user->role?->label(),
            ],
            'role' => $m->role,
            'can_view_responses' => $m->role === 'advocate' ? true : $m->can_view_responses,
            'secrecy_declared_at' => $m->secrecy_declared_at?->toIso8601String(),
        ];
    }
}

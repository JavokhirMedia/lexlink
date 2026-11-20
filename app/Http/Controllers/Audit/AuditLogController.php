<?php

namespace App\Http\Controllers\Audit;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CaseMember;
use App\Models\LegalCase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit journal.
 *  - admin: everything
 *  - advocate: logs of own cases (owned or joined as advocate) + own actions
 *  - lawyer: own actions only
 */
class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'action' => ['nullable', Rule::enum(AuditAction::class)],
            'case_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = $this->visibleTo($user)
            ->with('user:id,name')
            ->when($filters['action'] ?? null, fn (Builder $q, string $a) => $q->where('action', $a))
            ->when($filters['case_id'] ?? null, fn (Builder $q, $id) => $q->where('case_id', (int) $id))
            ->when($filters['date_from'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '>=', Carbon::parse($d)->startOfDay()))
            ->when($filters['date_to'] ?? null, fn (Builder $q, $d) => $q->where('created_at', '<=', Carbon::parse($d)->endOfDay()))
            ->orderByDesc('created_at')->orderByDesc('id');

        $entries = $query->paginate(25)->withQueryString()->through(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action->value,
            'action_label' => $log->action->label(),
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name] : null,
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'case_id' => $log->case_id,
            'meta' => $log->meta,
            'ip' => $log->ip,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);

        return Inertia::render('audit/index', [
            'entries' => $entries,
            'filters' => [
                'action' => $filters['action'] ?? null,
                'case_id' => isset($filters['case_id']) ? (int) $filters['case_id'] : null,
                'date_from' => $filters['date_from'] ?? null,
                'date_to' => $filters['date_to'] ?? null,
            ],
            'actions' => array_map(fn (AuditAction $a) => ['value' => $a->value, 'label' => $a->label()], AuditAction::cases()),
            'cases' => $this->caseOptions($user),
            'scope' => match (true) {
                $user->isAdmin() => 'all',
                $user->isAdvocate() => 'advocate',
                default => 'own',
            },
        ]);
    }

    /** @return Builder<AuditLog> */
    private function visibleTo(User $user): Builder
    {
        $query = AuditLog::query();

        if ($user->isAdmin()) {
            return $query;
        }

        if ($user->isAdvocate()) {
            return $query->where(fn (Builder $q) => $q
                ->where('user_id', $user->id)
                ->orWhereIn('case_id', $this->advocateCaseIds($user)));
        }

        return $query->where('user_id', $user->id);
    }

    /** @return Collection<int, int> */
    private function advocateCaseIds(User $user)
    {
        return LegalCase::where('owner_id', $user->id)->pluck('id')
            ->merge(CaseMember::where('user_id', $user->id)->where('role', 'advocate')->pluck('case_id'))
            ->unique()->values();
    }

    /** @return list<array{id: int, title: string}> */
    private function caseOptions(User $user): array
    {
        $query = LegalCase::query()->select(['id', 'title'])->orderByDesc('id')->limit(200);

        if (! $user->isAdmin()) {
            $ids = $user->isAdvocate()
                ? $this->advocateCaseIds($user)
                : CaseMember::where('user_id', $user->id)->pluck('case_id');
            $query->whereIn('id', $ids);
        }

        return $query->get()->map(fn (LegalCase $c) => ['id' => $c->id, 'title' => $c->title])->all();
    }
}

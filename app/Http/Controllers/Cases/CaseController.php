<?php

namespace App\Http\Controllers\Cases;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Http\Controllers\Controller;
use App\Models\AdvocateLawyerLink;
use App\Models\CaseMember;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\LegalBasisVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CaseController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status');

        $cases = LegalCase::query()
            ->whereHas('memberships', fn ($q) => $q->where('user_id', $user->id))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%")
                ->orWhere('court_case_number', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'closed'], true), fn ($q) => $q->where('status', $status))
            ->with(['owner:id,name', 'memberships.user:id,name,role'])
            ->withCount('legalBases')
            ->latest()
            ->get();

        $counts = DataRequest::query()
            ->whereIn('case_id', $cases->pluck('id'))
            ->selectRaw('case_id, status, count(*) as total')
            ->groupBy('case_id', 'status')
            ->get()
            ->groupBy('case_id');

        return Inertia::render('cases/index', [
            'cases' => $cases->map(fn (LegalCase $c) => [
                ...CasePresenter::legalCase($c),
                'members' => $c->memberships->map(fn (CaseMember $m) => ['id' => $m->user->id, 'name' => $m->user->name, 'role' => $m->role])->values(),
                'bases_count' => $c->legal_bases_count,
                'request_counts' => collect($counts->get($c->id, []))
                    ->mapWithKeys(fn ($row) => [($row->status instanceof DataRequestStatus ? $row->status->value : $row->status) => (int) $row->total])
                    ->all(),
            ])->values(),
            'filters' => ['search' => $search, 'status' => $status],
            'status_labels' => collect(DataRequestStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
            'can_create' => $user->isAdvocate() || $user->isLawyer(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('cases/create', [
            'categories' => collect(CasePresenter::CATEGORIES)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'advocates' => $user->isLawyer() ? $this->acceptedAdvocates($user) : [],
            'is_lawyer' => $user->isLawyer(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('create', LegalCase::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['required', 'string', 'max:255'],
            'court_case_number' => ['nullable', 'string', 'max:64'],
            'category' => ['required', Rule::in(array_keys(CasePresenter::CATEGORIES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'parties' => ['nullable', 'array', 'max:20'],
            'parties.*.role' => ['required', 'string', 'max:64'],
            'parties.*.name' => ['required', 'string', 'max:255'],
            'advocate_id' => [Rule::requiredIf($user->isLawyer()), 'nullable', 'integer'],
        ], [
            'advocate_id.required' => 'Ish biriktiriladigan advokatni tanlang.',
        ]);

        $advocate = $user;
        if ($user->isLawyer()) {
            $advocate = collect($this->acceptedAdvocates($user))->firstWhere('id', (int) $data['advocate_id']);
            if ($advocate === null) {
                return back()->withErrors(['advocate_id' => 'Siz bu advokat jamoasida emassiz.']);
            }
            $advocate = User::findOrFail($advocate['id']);
        }

        $case = DB::transaction(function () use ($data, $user, $advocate) {
            $case = LegalCase::create([
                'owner_id' => $advocate->id,
                'title' => $data['title'],
                'client_name' => $data['client_name'],
                'court_case_number' => $data['court_case_number'] ?? null,
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'parties' => array_values($data['parties'] ?? []),
                'status' => 'active',
            ]);

            $case->memberships()->create(['user_id' => $advocate->id, 'role' => 'advocate', 'can_view_responses' => true]);
            if ($user->isLawyer()) {
                $case->memberships()->create(['user_id' => $user->id, 'role' => 'lawyer', 'can_view_responses' => false]);
            }

            return $case;
        });

        $audit->log(AuditAction::CaseCreated, $case, ['title' => $case->title, 'owner_id' => $advocate->id], $case->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ish yaratildi.']);

        return to_route('cases.show', $case);
    }

    public function show(Request $request, LegalCase $legalCase, LegalBasisVerifier $verifier): Response
    {
        $case = $legalCase->load(['owner:id,name', 'memberships.user.advocateProfile', 'legalBases.creator:id,name']);
        Gate::authorize('view', $case);

        $user = $request->user();
        $membership = $case->memberships->firstWhere('user_id', $user->id);
        $canManage = Gate::allows('manageMembers', $case);

        $memberIds = $case->memberships->pluck('user_id');
        $availableLawyers = $canManage
            ? AdvocateLawyerLink::query()
                ->where('advocate_id', $case->owner_id)->where('status', 'accepted')->whereNotNull('lawyer_id')
                ->whereNotIn('lawyer_id', $memberIds)
                ->with('lawyer:id,name,email')->get()
                ->map(fn ($l) => ['id' => $l->lawyer->id, 'name' => $l->lawyer->name, 'email' => $l->lawyer->email])
                ->values()
            : [];

        $requests = $case->dataRequests()->latest()->get();

        return Inertia::render('cases/show', [
            'case' => CasePresenter::legalCase($case),
            'members' => $case->memberships->sortBy(fn ($m) => $m->role === 'advocate' ? 0 : 1)
                ->map(fn ($m) => CasePresenter::member($m))->values(),
            'membership' => [
                'role' => $membership->role,
                'can_view_responses' => Gate::allows('viewResponses', $case) || (bool) $membership->can_view_responses,
                'secrecy_declared_at' => $membership->secrecy_declared_at?->toIso8601String(),
            ],
            'can' => [
                'create_request' => Gate::allows('createRequest', $case),
                'sign' => Gate::allows('sign', $case),
                'manage_members' => $canManage,
                'add_basis' => Gate::allows('addLegalBasis', $case),
                'view_responses' => Gate::allows('viewResponses', $case),
            ],
            'requests' => $requests->map(fn ($r) => CasePresenter::request($r))->values(),
            'bases' => $case->legalBases->sortByDesc('id')->map(fn ($b) => CasePresenter::basis($b))->values(),
            'coverage' => $verifier->coverage($case),
            'available_lawyers' => $availableLawyers,
            'request_types' => collect(DataRequestType::cases())->map(fn (DataRequestType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'accepted_bases' => array_map(fn (LegalBasisType $b) => $b->value, $t->acceptedBases()),
                'secrecy_regime' => $t->secrecyRegime(),
            ])->values(),
            'advocate_order_types' => LegalBasisVerifier::ADVOCATE_ORDER_TYPES,
        ]);
    }

    /** @return list<array{id: int, name: string}> */
    private function acceptedAdvocates(User $lawyer): array
    {
        return AdvocateLawyerLink::query()
            ->where('lawyer_id', $lawyer->id)->where('status', 'accepted')
            ->with('advocate:id,name,role')->get()
            ->pluck('advocate')->filter(fn ($a) => $a?->isAdvocate())
            ->unique('id')->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all();
    }
}

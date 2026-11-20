<?php

namespace App\Http\Controllers\Requests;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Http\Controllers\Cases\CasePresenter;
use App\Http\Controllers\Controller;
use App\Jobs\FetchGovResultJob;
use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalBasis;
use App\Models\LegalCase;
use App\Models\User;
use App\Policies\DataRequestPolicy;
use App\Services\Ai\AiAnalysisPresenter;
use App\Services\Ai\AiService;
use App\Services\DataRequestService;
use App\Services\Requests\RequestTextTemplate;
use App\Support\Pinfl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DataRequestController extends Controller
{
    public function __construct(private DataRequestService $service) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(DataRequestStatus::class)],
            'type' => ['nullable', Rule::enum(DataRequestType::class)],
        ]);

        $query = DataRequest::query()->with('legalCase:id,title,court_case_number')->latest();
        if (! $user->isAdmin()) {
            $query->whereIn('case_id', $user->cases()->select('cases.id'));
        }
        if ($filters['status'] ?? null) {
            $query->where('status', $filters['status']);
        }
        if ($filters['type'] ?? null) {
            $query->where('type', $filters['type']);
        }

        $page = $query->paginate(20)->withQueryString();

        return Inertia::render('requests/index', [
            'requests' => [
                'data' => collect($page->items())->map(fn (DataRequest $r) => $this->listItem($r))->values(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
                'prev_page_url' => $page->previousPageUrl(),
                'next_page_url' => $page->nextPageUrl(),
            ],
            'filters' => ['status' => $filters['status'] ?? null, 'type' => $filters['type'] ?? null],
            'statuses' => collect(DataRequestStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
            'types' => $this->types(),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        $caseId = $request->integer('case');
        $case = $caseId ? LegalCase::find($caseId) : null;
        if (! $case) {
            return redirect()->route('cases.index')->with('error', "So'rov ish ichidan yaratiladi — avval ishni tanlang");
        }
        Gate::authorize('createRequest', $case);
        $user = $request->user();

        return Inertia::render('requests/create', [
            'case' => CasePresenter::legalCase($case),
            'bases' => $case->legalBases()->latest('id')->get()->map(fn ($b) => CasePresenter::basis($b))->values(),
            'subjects' => $this->subjects($case),
            'types' => $this->types(),
            'defaults' => [
                'type' => $request->query('type') && DataRequestType::tryFrom($request->query('type')) ? $request->query('type') : 'salary',
                'period_from' => now()->subMonths(6)->format('Y-m'),
                'period_to' => now()->subMonth()->format('Y-m'),
            ],
            'quote' => $this->service->quote($user),
            'can_sign' => $this->canSign($user, $case),
            'ai_available' => app()->bound(AiService::class),
        ]);
    }

    /** Live basis check for the create page (JSON, POST so PINFL never lands in a URL). */
    public function checkBasis(Request $request): JsonResponse
    {
        $data = $this->validateForm($request, forCheck: true);
        $case = LegalCase::findOrFail($data['case_id']);
        Gate::authorize('createRequest', $case);

        $draft = $this->draftFrom($case, $request->user(), $data);

        return response()->json($this->presentCheck($this->service->checkBasis($draft)));
    }

    /** "AI yordamida yozish" — AI draft, falling back to a formal Uzbek template. */
    public function draftText(Request $request, RequestTextTemplate $template): JsonResponse
    {
        $data = $this->validateForm($request, forCheck: true);
        $case = LegalCase::findOrFail($data['case_id']);
        Gate::authorize('createRequest', $case);

        $draft = $this->draftFrom($case, $request->user(), $data);

        if (app()->bound(AiService::class)) {
            try {
                $text = trim(app(AiService::class)->draftRequestText($draft));
                if ($text !== '') {
                    return response()->json(['text' => $text, 'source' => 'ai', 'disclaimer' => config('lexlink.ai.disclaimer')]);
                }
            } catch (Throwable $e) {
                Log::warning('AI request draft failed, using template: '.$e->getMessage());
            }
        }

        return response()->json(['text' => $template->render($draft, $request->user()), 'source' => 'template', 'disclaimer' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateForm($request);
        $case = LegalCase::findOrFail($data['case_id']);
        Gate::authorize('createRequest', $case);
        $user = $request->user();

        $r = $this->service->create($case, $user, $this->attributes($case, $data));

        $intent = $data['intent'] ?? 'draft';
        if ($intent === 'signature') {
            $this->service->requestSignature($r, $user);
            Inertia::flash('toast', ['type' => 'success', 'message' => "So'rov advokat imzosiga yuborildi"]);
        } elseif ($intent === 'submit') {
            return redirect()->route('requests.show', ['dataRequest' => $r->id, 'sign' => 1]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => "So'rov loyihasi saqlandi"]);
        }

        return redirect()->route('requests.show', $r);
    }

    public function update(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        Gate::authorize('update', $dataRequest);
        $data = $this->validateForm($request, forUpdate: true);
        $this->service->update($dataRequest, $this->attributes($dataRequest->legalCase, $data + ['case_id' => $dataRequest->case_id]));
        Inertia::flash('toast', ['type' => 'success', 'message' => "So'rov yangilandi"]);

        return back();
    }

    public function requestSignature(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        Gate::authorize('requestSignature', $dataRequest);
        $this->service->requestSignature($dataRequest, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => "So'rov advokat imzosiga yuborildi"]);

        return back();
    }

    public function sign(Request $request, DataRequest $dataRequest): RedirectResponse
    {
        Gate::authorize('view', $dataRequest);
        $request->validate(['key_id' => ['required', 'string'], 'confirm' => ['accepted']], [
            'key_id.required' => 'ERI kalitini tanlang',
            'confirm.accepted' => 'Imzolashni tasdiqlang',
        ]);

        $this->service->signAndSubmit($dataRequest, $request->user());
        Inertia::flash('toast', ['type' => 'success', 'message' => "So'rov imzolandi va idoraga yuborildi"]);

        return redirect()->route('requests.show', $dataRequest);
    }

    public function show(Request $request, DataRequest $dataRequest): Response
    {
        Gate::authorize('view', $dataRequest);
        $user = $request->user();
        $r = $dataRequest;

        // sync queue (local dev): the poll comes from the page instead of a delayed job
        if ($r->status === DataRequestStatus::Processing && config('queue.default') === 'sync') {
            try {
                FetchGovResultJob::dispatchSync($r->id);
                $r->refresh();
            } catch (Throwable $e) {
                Log::warning('Inline gov poll failed: '.$e->getMessage());
            }
        }

        $r->load(['legalCase', 'legalBasis', 'creator', 'signer.advocateProfile', 'response']);
        $case = $r->legalCase;
        $canViewResponse = DataRequestPolicy::canViewResponses($user, $case);
        $isAdmin = $user->isAdmin();
        $canSign = $this->canSign($user, $case) && in_array($r->status, [DataRequestStatus::Draft, DataRequestStatus::AwaitingSignature], true);
        $editable = in_array($r->status, [DataRequestStatus::Draft, DataRequestStatus::AwaitingSignature], true);

        return Inertia::render('requests/show', [
            'request' => fn () => $this->detail($r->fresh(['legalCase', 'legalBasis', 'creator', 'signer.advocateProfile'])),
            'case' => ['id' => $case->id, 'title' => $case->title, 'court_case_number' => $case->court_case_number],
            'timeline' => fn () => $this->timeline($r->fresh()),
            'basis_check' => fn () => $editable && ! $isAdmin ? $this->presentCheck($this->service->checkBasis($r)) : null,
            'permissions' => [
                'can_edit' => Gate::allows('update', $r),
                'can_request_signature' => Gate::allows('requestSignature', $r),
                'can_sign' => $canSign,
                'can_view_response' => $canViewResponse,
                'needs_secrecy_declaration' => DataRequestPolicy::needsSecrecyDeclaration($user, $case),
                'is_admin' => $isAdmin,
                'is_lawyer' => $user->isLawyer(),
            ],
            'quote' => $canSign ? $this->service->quote($user) : null,
            'signer_key' => $canSign ? $this->signerKey($user) : null,
            'open_sign' => $canSign && $request->boolean('sign'),
            'response' => fn () => ResponseController::present($r->fresh('response'), $user, $canViewResponse),
            'ai_analysis' => fn () => $canViewResponse ? $this->latestAnalysis($r) : null,
        ]);
    }

    // ---------------------------------------------------------------- helpers

    private function validateForm(Request $request, bool $forCheck = false, bool $forUpdate = false): array
    {
        $type = DataRequestType::tryFrom((string) $request->input('type'));

        return $request->validate([
            'case_id' => [$forUpdate ? 'nullable' : 'required', 'integer', 'exists:cases,id'],
            'type' => ['required', Rule::enum(DataRequestType::class)],
            'subject_basis_id' => ['nullable', 'integer'],
            'subject_pinfl' => ['nullable', 'required_without:subject_basis_id', 'regex:/^\d{14}$/'],
            'subject_name' => ['nullable', 'string', 'max:255'],
            'params' => ['nullable', 'array'],
            'params.period_from' => [$type === DataRequestType::Salary && ! $forCheck ? 'required' : 'nullable', 'date_format:Y-m'],
            'params.period_to' => [$type === DataRequestType::Salary && ! $forCheck ? 'required' : 'nullable', 'date_format:Y-m', 'after_or_equal:params.period_from'],
            'params.cadastral_number' => ['nullable', 'string', 'max:32'],
            'params.court_case_number' => ['nullable', 'string', 'max:64'],
            'purpose' => [$forCheck ? 'nullable' : 'required', 'string', 'max:2000'],
            'request_text' => [$forCheck ? 'nullable' : 'required', 'string', 'max:20000'],
            'intent' => ['nullable', 'in:draft,signature,submit'],
        ], [
            'subject_pinfl.required_without' => 'Shaxsni tanlang yoki JShShIR kiriting',
            'subject_pinfl.regex' => 'JShShIR 14 ta raqamdan iborat bo\'lishi kerak',
            'params.period_from.required' => 'Davr boshini kiriting',
            'params.period_to.required' => 'Davr oxirini kiriting',
            'params.period_to.after_or_equal' => "Davr oxiri boshidan oldin bo'lmasligi kerak",
            'purpose.required' => "So'rov maqsadini kiriting",
            'request_text.required' => "So'rov matnini kiriting",
        ]);
    }

    /** Resolve the subject from a case basis (PINFL never travels to the browser) or typed PINFL. */
    private function attributes(LegalCase $case, array $data): array
    {
        $pinfl = $data['subject_pinfl'] ?? null;
        $name = $data['subject_name'] ?? null;
        if (! empty($data['subject_basis_id'])) {
            $basis = $case->legalBases()->whereKey($data['subject_basis_id'])->first();
            if ($basis) {
                $pinfl = $basis->subject_pinfl;
                $name = $name ?: $basis->subject_name;
            }
        }

        return [
            'type' => $data['type'],
            'subject_pinfl' => $pinfl,
            'subject_name' => $name,
            'params' => $data['params'] ?? [],
            'purpose' => $data['purpose'] ?? null,
            'request_text' => $data['request_text'] ?? null,
        ];
    }

    private function draftFrom(LegalCase $case, User $user, array $data): DataRequest
    {
        $attrs = $this->attributes($case, $data);
        $draft = new DataRequest([
            'case_id' => $case->id,
            'type' => DataRequestType::from($attrs['type']),
            'subject_pinfl' => $attrs['subject_pinfl'],
            'subject_name' => $attrs['subject_name'],
            'params' => $attrs['params'],
            'purpose' => $attrs['purpose'],
            'request_text' => $attrs['request_text'],
            'status' => DataRequestStatus::Draft,
            'created_by' => $user->id,
        ]);
        $draft->subject_pinfl_masked = Pinfl::mask($attrs['subject_pinfl']);
        $draft->setRelation('legalCase', $case);

        return $draft;
    }

    private function presentCheck(array $check): array
    {
        /** @var LegalBasis|null $b */
        $b = $check['basis'];

        return [
            'ok' => $check['ok'],
            'reason' => $check['reason'],
            'basis' => $b ? [
                'id' => $b->id,
                'type' => $b->type->value,
                'type_label' => $b->type->label(),
                'number' => $b->number,
                'court_name' => $b->court_name,
                'expires_at' => $b->expires_at?->toDateString(),
                'subject_name' => $b->subject_name,
                'subject_pinfl_masked' => $b->subject_pinfl_masked,
            ] : null,
        ];
    }

    private function subjects(LegalCase $case): array
    {
        return $case->legalBases()->whereNotNull('subject_pinfl_hash')->latest('id')->get()
            ->unique('subject_pinfl_hash')
            ->map(fn (LegalBasis $b) => [
                'basis_id' => $b->id,
                'name' => $b->subject_name,
                'pinfl_masked' => $b->subject_pinfl_masked,
                'basis_number' => $b->number,
                'basis_type_label' => $b->type->label(),
            ])->values()->all();
    }

    private function types(): array
    {
        return collect(DataRequestType::cases())->map(fn (DataRequestType $t) => [
            'value' => $t->value,
            'label' => $t->label(),
            'agency' => $t->agency(),
            'secrecy_regime' => $t->secrecyRegime(),
            'accepted_bases' => array_map(fn (LegalBasisType $b) => ['value' => $b->value, 'label' => $b->label()], $t->acceptedBases()),
        ])->values()->all();
    }

    private function canSign(User $user, LegalCase $case): bool
    {
        return $user->hasActiveLicense() && $this->service->isAdvocateMember($case, $user);
    }

    private function listItem(DataRequest $r): array
    {
        return CasePresenter::request($r) + [
            'case' => $r->legalCase ? ['id' => $r->legalCase->id, 'title' => $r->legalCase->title, 'court_case_number' => $r->legalCase->court_case_number] : null,
        ];
    }

    private function detail(DataRequest $r): array
    {
        return CasePresenter::request($r) + [
            'creator' => $r->creator ? ['id' => $r->creator->id, 'name' => $r->creator->name] : null,
            'signer' => $r->signer ? [
                'id' => $r->signer->id,
                'name' => $r->signer->name,
                'license_number' => $r->signer->advocateProfile?->license_number,
            ] : null,
            'signature' => $r->signature,
            'failure_reason' => $r->failure_reason,
            'charged_from_subscription' => (bool) $r->charged_from_subscription,
            'legal_basis' => $r->legalBasis ? [
                'id' => $r->legalBasis->id,
                'type' => $r->legalBasis->type->value,
                'type_label' => $r->legalBasis->type->label(),
                'number' => $r->legalBasis->number,
                'court_name' => $r->legalBasis->court_name,
                'expires_at' => $r->legalBasis->expires_at?->toDateString(),
            ] : null,
            'secrecy_regime' => $r->type->secrecyRegime(),
            'updated_at' => $r->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array{key: string, label: string, state: string, at: ?string, note: ?string}> */
    private function timeline(DataRequest $r): array
    {
        $order = ['draft', 'awaiting_signature', 'submitted', 'processing', 'final'];
        $current = match ($r->status) {
            DataRequestStatus::Completed, DataRequestStatus::Rejected, DataRequestStatus::Failed => 'final',
            default => $r->status->value,
        };
        $idx = array_search($current, $order, true);

        $final = match ($r->status) {
            DataRequestStatus::Rejected => ['label' => 'Rad etildi', 'tone' => 'danger', 'note' => $r->rejection_reason],
            DataRequestStatus::Failed => ['label' => 'Texnik xato', 'tone' => 'danger', 'note' => $r->failure_reason],
            default => ['label' => 'Tayyor', 'tone' => 'success', 'note' => null],
        };

        $steps = [
            ['key' => 'draft', 'label' => 'Loyiha', 'at' => $r->created_at, 'note' => null],
            ['key' => 'awaiting_signature', 'label' => 'Imzo kutilmoqda', 'at' => null, 'note' => null],
            ['key' => 'submitted', 'label' => 'Yuborildi', 'at' => $r->submitted_at ?? $r->signed_at, 'note' => $r->signer ? 'Imzoladi: '.$r->signer->name : null],
            ['key' => 'processing', 'label' => 'Jarayonda', 'at' => null, 'note' => $r->gov_request_id ? 'Idora raqami: '.$r->gov_request_id : null],
            ['key' => 'final', 'label' => $final['label'], 'at' => $r->completed_at, 'note' => $final['note']],
        ];

        return array_map(function ($s, $i) use ($idx, $r, $final) {
            $state = $i < $idx ? 'done' : ($i === $idx ? ($r->status->isFinal() ? $final['tone'] : 'current') : 'upcoming');

            return [
                'key' => $s['key'],
                'label' => $s['label'],
                'state' => $state,
                'at' => $s['at']?->toIso8601String(),
                'note' => $s['note'],
            ];
        }, $steps, array_keys($steps));
    }

    private function signerKey(User $user): array
    {
        $profile = $user->advocateProfile;
        $stir = str_pad((string) (crc32('stir'.$user->id) % 1_000_000_000), 9, '3', STR_PAD_LEFT);

        return [
            'id' => 'demo-eri-'.$user->id,
            'owner' => $user->name,
            'stir' => $stir,
            'license_number' => $profile?->license_number,
            'issuer' => 'DEMO: Elektron raqamli imzo markazi',
            'valid_to' => now()->addYear()->startOfYear()->toDateString(),
        ];
    }

    private function latestAnalysis(DataRequest $r): ?array
    {
        return AiAnalysisPresenter::present(
            AiAnalysis::where('data_request_id', $r->id)->where('kind', 'summary')->latest('id')->first()
        );
    }
}

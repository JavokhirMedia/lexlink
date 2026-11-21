<?php

namespace App\Services\Ai;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Enums\LegalBasisType;
use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\Ai\Anonymization\AnonymizationContext;
use App\Services\Ai\Anonymization\AnonymizationGateway;
use App\Services\Ai\Anonymization\AnonymizationReport;
use App\Services\Ai\Anonymization\ResponseProjector;
use App\Services\AuditLogger;
use App\Services\BillingService;
use App\Services\Requests\ResponseSummarizer;
use Illuminate\Support\Collection;

/**
 * Shared pipeline for every AI operation:
 *   1. build an AnonymizationContext for the case / request (every known person, identifier and payload leaf),
 *   2. project the facts through an allowlist (ResponseProjector) — people / organisations / case numbers become
 *      placeholders, as do addresses / plates / VIN / cadastral / account numbers / TIN / birth dates;
 *      amounts and event dates stay so the analysis remains useful,
 *   3. compute numbers in PHP (AlimonyCalculator, ResponseSummarizer),
 *   4. retrieve lex.uz articles (deterministic legal refs + RAG search),
 *   5. render a versioned prompt and complete() it — only through AnonymizationGateway (leak guard) to Gemini,
 *      or a deterministic template,
 *   6. persist AiAnalysis (masked output + display map + exact sent text + anonymisation report), audit, return.
 *
 * Subclasses only decide how text is generated (complete()).
 */
abstract class BaseAiService implements AiService
{
    public function __construct(
        protected LawKnowledgeBase $kb,
        protected AlimonyCalculator $calculator,
        protected PromptRepository $prompts,
        protected AuditLogger $audit,
        protected AnonymizationGateway $gateway,
        protected ResponseProjector $projector,
    ) {}

    /**
     * Generate text for a prompt built from projected / masked facts. Must return the deterministic $fallback when
     * generation is unavailable or the gateway blocks the call.
     *
     * @param  list<string>  $public  verbatim public law text embedded in $prompt
     * @return array{text: string, tokens: int, model: string, sent: ?string, report: array}
     */
    abstract protected function complete(string $prompt, string $fallback, AnonymizationContext $ctx, array $options = [], array $public = []): array;

    // ------------------------------------------------------------------ AiService

    public function draftRequestText(DataRequest $request): string
    {
        $request->loadMissing(['legalCase.owner.advocateProfile', 'legalBasis', 'creator', 'signer']);
        $case = $request->legalCase;
        $ctx = $this->contextFor($case, $request, 'request_text');
        $masker = $ctx->masker;
        $type = $request->type;

        $refs = [['advokatura', '7-1'], ['advokatura', '6'], ['shaxsga-doir-malumotlar', '18'], ['advokatlik-kafolatlari', '4']];
        $refs = [...$refs, ...match ($type) {
            DataRequestType::Salary, DataRequestType::TaxInfo, DataRequestType::BusinessShares => [['soliq-kodeksi', '29']],
            DataRequestType::BankAccounts => [['bank-siri', '10']],
            DataRequestType::BorderCrossing => [['shaxsga-doir-malumotlar', '25']],
            default => [],
        }];
        $sources = $this->sources($refs, null);
        $context = $this->kb->context($sources);

        $advocate = $this->advocateFor($request);
        $license = $advocate?->advocateProfile?->license_number;
        $vars = [
            'advocate' => $advocate ? ($ctx->person($advocate->name) ?? $masker->mask($advocate->name)) : '[___]',
            'client' => $masker->mask($case->client_name),
            'basis' => $this->basisText($request, $ctx),
            'data_requested' => $this->dataRequested($type),
            'period' => $this->periodText($request->params ?? []),
            'subject' => $masker->mask($request->subject_name ?: '[___]'),
            'subject_pinfl' => $this->subjectPinflPlaceholder($ctx, $request),
            'secrecy_hint' => $this->secrecyHint($type),
            'attachments' => $this->requestAttachments($request),
            'license' => $license ? $ctx->identifier('LITSENZIYA', 'license', $license) : '[___]',
            'facts' => $this->requestFacts($request, $ctx),
            'sources' => $context,
        ];

        [$prompt, $version] = $this->prompts->render('request_text', $vars);
        $result = $this->complete($prompt, $this->fallbackRequestText($vars, $type, $sources), $ctx, ['max_tokens' => 2048], [$context]);

        $this->persist('request_text', $case, $request, $result, $masker, $version, $sources, ['type' => $type->value]);

        return $masker->unmask($result['text']);
    }

    public function analyzeResponse(DataRequest $request): AiAnalysis
    {
        $request->loadMissing(['legalCase.owner', 'legalBasis', 'response']);
        $case = $request->legalCase;
        $ctx = $this->contextFor($case, $request, 'analyze_response');
        $masker = $ctx->masker;
        $data = $this->responseData($request);
        $maskedData = $masker->maskArray($data); // local only: fallback templates + structured facts
        $projected = $this->projector->project($request->type, $data, $ctx); // the only form of the payload the AI sees
        $facts = $this->requestFacts($request, $ctx);

        if ($request->type === DataRequestType::Salary) {
            $children = AlimonyCalculator::detectChildren($request->params ?? [], $case->description, $request->purpose, $case->title);
            $calc = $this->calculator->calculate($data['months'] ?? [], $children);
            $sources = $this->sources(
                [['oila-kodeksi', '99'], ['oila-kodeksi', '104'], ['oila-kodeksi', '102'], ['mehnat-kodeksi', '270']],
                null,
            );
            $context = $this->kb->context($sources);
            [$prompt, $version] = $this->prompts->render('analyze_salary', [
                'facts' => $facts,
                'payload' => $this->json($projected),
                'calculation' => $this->json($this->calcForPrompt($calc)),
                'sources' => $context,
            ]);
            $fallback = $this->fallbackSalarySummary($calc, $maskedData, $sources);
            $structured = ['type' => 'salary', 'alimony' => $calc, 'children_detected' => $children];
        } else {
            [$hint, $refs, $query] = $this->assetMeaning($request->type);
            $sources = $this->sources($refs, $query, 3);
            $context = $this->kb->context($sources);
            $numbers = $this->domainNumbers($request, $data);
            [$prompt, $version] = $this->prompts->render('analyze_asset', [
                'type_label' => $request->type->label(),
                'meaning_hint' => $hint,
                'facts' => $facts,
                // PHP-computed figures travel with the payload so the model never does arithmetic
                'payload' => $this->json($numbers ? ['javob' => $projected, 'hisoblangan_raqamlar_php' => $this->projector->numbers($numbers, $ctx)] : $projected),
                'sources' => $context,
            ]);
            $fallback = $numbers
                ? $this->fallbackDomainSummary($request->type, $numbers, $maskedData)
                : $this->fallbackAssetSummary($request->type, $maskedData, $sources);
            $structured = ['type' => $request->type->value, 'facts' => $this->assetFacts($request->type, $maskedData)] + ($numbers ? ['numbers' => $numbers] : []);
        }

        $result = $this->complete($prompt, $fallback, $ctx, [], [$context]);
        $analysis = $this->persist('summary', $case, $request, $result, $masker, $version, $sources, $structured);
        $this->audit->log(AuditAction::AiAnalysis, $analysis, ['kind' => 'summary', 'request_id' => $request->id, 'model' => $analysis->model, 'leak_check' => $result['report']['leak_check'] ?? null], $case->id);

        return $analysis;
    }

    public function draftClaim(LegalCase $case, array $options = []): AiAnalysis
    {
        $case->loadMissing(['owner.advocateProfile', 'dataRequests.response', 'dataRequests.legalBasis']);
        $user = $options['user'] ?? auth()->user();
        $ctx = $this->contextFor($case, null, 'draft_claim', $user);
        $masker = $ctx->masker;

        $completed = $case->dataRequests->filter(fn (DataRequest $r) => $r->status === DataRequestStatus::Completed && $r->response)->values();
        $salary = $completed->filter(fn (DataRequest $r) => $r->type === DataRequestType::Salary)->sortByDesc('completed_at')->first();

        $children = isset($options['children']) && (int) $options['children'] > 0
            ? (int) $options['children']
            : AlimonyCalculator::detectChildren($salary?->params ?? [], $case->description, $case->title);
        $calc = $salary ? $this->calculator->calculate($this->responseData($salary)['months'] ?? [], $children) : null;

        $defendantName = $salary?->subject_name ?? $this->partyName($case, ['javobgar', 'defendant', 'respondent']) ?? $completed->first()?->subject_name;
        $defendant = $defendantName ? $masker->mask($defendantName) : '[___]';
        $claimant = $masker->mask($case->client_name);

        $responses = $completed->map(fn (DataRequest $r) => [
            'turi' => $r->type->label(),
            'idora' => $r->type->agency(),
            'olingan_sana' => $r->completed_at?->toDateString(),
            'malumot' => $this->projector->project($r->type, $this->responseData($r), $ctx),
        ])->all();

        $sources = $this->sources(
            [['oila-kodeksi', '98'], ['oila-kodeksi', '99'], ['oila-kodeksi', '104'], ['oila-kodeksi', '112'], ['fuqarolik-protsessual-kodeksi', '189'], ['fuqarolik-protsessual-kodeksi', '34'], ['fuqarolik-protsessual-kodeksi', '187']],
            $completed->contains(fn ($r) => in_array($r->type, self::ASSET_TYPES, true)) ? "da'voni ta'minlash choralari mol-mulkka xatlov" : null,
            2,
        );
        $context = $this->kb->context($sources);

        $court = trim((string) ($options['court'] ?? ''));
        $vars = [
            'court' => $court !== '' ? $ctx->identifier('SUD', 'court_name', $court) : "[___] tumanlararo fuqarolik ishlari bo'yicha sudi",
            'claimant' => $claimant,
            'defendant' => $defendant,
            'defendant_pinfl' => $salary ? $this->subjectPinflPlaceholder($ctx, $salary) : '[___]',
            'advocate' => $case->owner ? ($ctx->person($case->owner->name) ?? '[___]') : '[___]',
            'children_text' => $children ? "{$children} nafar" : 'bolalar soni koʻrsatilmagan — [___]',
            'attachments' => implode('; ', $this->claimAttachments($completed, $ctx)),
            'facts' => $this->caseFacts($case, $ctx),
            'responses' => $this->json($responses),
            'calculation' => $calc ? $this->json($this->calcForPrompt($calc) + ($this->projector->numbers($this->claimAssetNumbers($completed), $ctx) ?? [])) : "Ish haqi ma'lumoti hali olinmagan.",
            'sources' => $context,
        ];

        [$prompt, $version] = $this->prompts->render('draft_claim', $vars);
        $fallback = $this->fallbackClaim($vars, $calc, $children, $completed, $sources);

        $cost = 0;
        if ($user && class_exists(BillingService::class) && ($options['charge'] ?? true)) {
            $cost = app(BillingService::class)->chargeAi($user, 'draft_claim'); // may throw InsufficientBalance
        }

        $result = $this->complete($prompt, $fallback, $ctx, ['max_tokens' => 8192], [$context]);
        $analysis = $this->persist('draft_claim', $case, null, $result, $masker, $version, $sources, [
            'type' => 'draft_claim',
            'alimony' => $calc,
            'children' => $children,
            'responses_used' => $completed->pluck('id')->all(),
        ], $user, $cost);

        $this->audit->log(AuditAction::AiDraftClaim, $analysis, ['kind' => 'draft_claim', 'model' => $analysis->model, 'cost' => $cost, 'leak_check' => $result['report']['leak_check'] ?? null], $case->id, $user);

        return $analysis;
    }

    public function askLaw(string $question): array
    {
        $user = auth()->user();
        $ctx = $this->contextForUser($user instanceof User ? $user : null, 'ask_law');
        $masked = $ctx->masker->mask($question);
        $removed = AnonymizationReport::countPlaceholders($masked);
        $sources = $this->kb->search($masked, 6, $ctx);
        $context = $sources->isEmpty() ? '(manbalar topilmadi)' : $this->kb->context($sources);

        [$prompt, $version] = $this->prompts->render('ask_law', [
            'question' => $masked,
            'sources' => $context,
        ]);
        $result = $this->complete($prompt, $this->fallbackAnswer($sources), $ctx, ['max_tokens' => 4096], $sources->isEmpty() ? [] : [$context]);

        return [
            'answer' => $ctx->masker->unmask($result['text']),
            'citations' => $this->kb->citations($sources),
            'model' => $result['model'],
            'tokens' => $result['tokens'],
            'prompt_version' => $version,
            'disclaimer' => (string) config('lexlink.ai.disclaimer'),
            'pii_removed' => $removed !== [],
            'sent_payload' => $result['sent'],
            'anonymization' => $result['report'],
        ];
    }

    // ------------------------------------------------------------------ anonymisation context

    /**
     * Context pre-loaded with every person, identifier and payload value known for the case
     * (deterministic placeholders + the leak guard's known-PII set).
     */
    public function contextFor(LegalCase $case, ?DataRequest $request = null, string $operation = 'ai', ?User $user = null): AnonymizationContext
    {
        $case->loadMissing(['owner.advocateProfile', 'legalBases', 'dataRequests.response', 'memberships.user']);
        $user ??= auth()->user();
        $ctx = new AnonymizationContext(new PiiMasker, $operation, $request ?? $case, $case->id, $user instanceof User ? $user : null);

        $requests = $request ? collect([$request])->merge($case->dataRequests->where('id', '!=', $request->id)) : $case->dataRequests;
        // order = placeholder numbering: case people first, then request subjects (the current request first)
        $this->registerCase($ctx, $case, subjects: $requests);
        foreach ($requests as $r) {
            $ctx->known('request_number', $r->gov_request_id);
            if ($r->response && ! $r->response->isPurged() && is_array($payload = $r->response->payload)) {
                $ctx->collect($payload);
            }
            foreach ([$r->signer, $r->creator] as $u) {
                $this->registerUser($ctx, $u);
            }
        }
        $this->registerUser($ctx, $user instanceof User ? $user : null);

        return $ctx;
    }

    /** askLaw: names of the parties of the user's own cases + the user's identifiers. */
    protected function contextForUser(?User $user, string $operation): AnonymizationContext
    {
        $ctx = new AnonymizationContext(new PiiMasker, $operation, null, null, $user);
        if (! $user) {
            return $ctx;
        }
        $cases = LegalCase::query()
            ->where(fn ($q) => $q->where('owner_id', $user->id)->orWhereHas('memberships', fn ($m) => $m->where('user_id', $user->id)))
            ->with(['legalBases', 'dataRequests', 'owner.advocateProfile'])
            ->latest('id')->limit(100)->get();
        foreach ($cases as $case) {
            $this->registerCase($ctx, $case, withMembers: false);
        }
        $this->registerUser($ctx, $user);

        return $ctx;
    }

    private function registerCase(AnonymizationContext $ctx, LegalCase $case, bool $withMembers = true, ?iterable $subjects = null): void
    {
        $ctx->person($case->owner?->name);
        $ctx->person($case->client_name);
        foreach ((array) $case->parties as $party) {
            $name = is_array($party) ? ($party['name'] ?? null) : null;
            AnonymizationContext::looksLikeOrganization((string) $name) ? $ctx->organization('KORXONA', $name) : $ctx->person($name);
            if (is_array($party)) {
                $ctx->collect(array_diff_key($party, ['name' => 1, 'role' => 1]));
            }
        }
        foreach ($subjects ?? $case->dataRequests as $r) {
            $ctx->person($r->subject_name);
            $ctx->pinfl($r->subject_pinfl);
            $ctx->known('masked_id', $r->subject_pinfl_masked);
        }
        $this->registerUser($ctx, $case->owner);
        if ($withMembers) {
            foreach ($case->memberships as $m) {
                $this->registerUser($ctx, $m->user);
            }
        }
        if ($case->court_case_number) {
            $ctx->identifier('ISH', 'case_number', $case->court_case_number);
        }
        foreach ($case->legalBases as $b) {
            $ctx->person($b->subject_name);
            $ctx->pinfl($b->subject_pinfl);
            if ($b->number) {
                $ctx->identifier($b->type === LegalBasisType::CourtDecision ? 'QAROR' : 'ORDER', 'decision_number', $b->number);
            }
            if (is_array($b->verification_payload)) {
                $ctx->collect($b->verification_payload);
            }
        }
    }

    private function registerUser(AnonymizationContext $ctx, ?User $u): void
    {
        if (! $u) {
            return;
        }
        $ctx->person($u->name);
        $ctx->known('phone', $u->phone);
        $ctx->known('email', $u->email);
        $ctx->pinfl($u->pinfl ?? null);
        if ($license = $u->advocateProfile?->license_number) {
            $ctx->known('license', $license);
        }
        if ($bureau = $u->advocateProfile?->bureau_name) {
            $ctx->organization('KORXONA', $bureau);
        }
    }

    // ------------------------------------------------------------------ helpers

    /** Persist an analysis: output stays masked; structured._pii holds the display map (names, masked ids only). */
    protected function persist(string $kind, LegalCase $case, ?DataRequest $request, array $result, PiiMasker $masker, string $version, iterable $sources, array $structured, ?User $user = null, int $cost = 0): AiAnalysis
    {
        $structured['disclaimer'] = (string) config('lexlink.ai.disclaimer');
        $structured['generator'] = str_starts_with($result['model'], 'fallback') ? 'fallback' : 'gemini';
        $structured['_pii'] = encrypt($masker->displayMap()); // display map holds real names — encrypted at rest, wiped on purge

        return AiAnalysis::create([
            'data_request_id' => $request?->id,
            'case_id' => $case->id,
            'created_by' => ($user ?? auth()->user())?->id,
            'kind' => $kind,
            'status' => 'completed',
            'model' => mb_substr($result['model'], 0, 64),
            'prompt_version' => $version,
            'output' => $result['text'],
            'sent_payload' => $result['sent'] ?? null,
            'anonymization_report' => $result['report'] ?? AnonymizationReport::notCalled(),
            'structured' => $structured,
            'citations' => $this->kb->citations($sources),
            'tokens' => $result['tokens'],
            'cost' => $cost,
        ]);
    }

    /**
     * Deterministic legal refs first, then RAG hits for $query (deduplicated). $query is a static, public string.
     *
     * @param  list<array{0: string, 1: string}>  $refs
     */
    protected function sources(array $refs, ?string $query, int $k = 4): Collection
    {
        $chunks = collect($this->kb->articles($refs)->all());
        if ($query) {
            $chunks = $chunks->merge($this->kb->search($query, $k)->all());
        }

        return $chunks->unique(fn ($c) => $c->law_document_id.'|'.$c->article_number)->values();
    }

    protected function responseData(DataRequest $request): array
    {
        $response = $request->response;
        if (! $response) {
            return [];
        }
        if (! $response->isPurged() && is_array($payload = $response->payload)) {
            return is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        }

        return (array) ($response->summary ?? []);
    }

    protected function requestFacts(DataRequest $r, AnonymizationContext $ctx): string
    {
        $m = $ctx->masker;
        $case = $r->legalCase;
        $lines = [
            "Ish: {$m->mask($case->title)}".($case->court_case_number ? ' (sud ishi № '.$ctx->identifier('ISH', 'case_number', $case->court_case_number).')' : '').($case->category ? ", toifa: {$m->mask($case->category)}" : ''),
            "Mijoz (advokat himoyasidagi shaxs): {$m->mask($case->client_name)}",
            "So'rov turi: {$r->type->label()}; idora: {$r->type->agency()}; rejim: {$r->type->secrecyRegime()}",
            'Subyekt: '.$m->mask($r->subject_name ?: '[___]').', JShShIR '.$this->subjectPinflPlaceholder($ctx, $r),
            'Davr: '.$this->periodText($r->params ?? []),
            'Huquqiy asos: '.$this->basisText($r, $ctx),
        ];
        if ($r->purpose) {
            $lines[] = 'Maqsad: '.$m->mask($r->purpose);
        }
        if ($case->description) {
            $lines[] = 'Ish tavsifi: '.$m->mask(mb_substr($case->description, 0, 1500));
        }

        return implode("\n", $lines);
    }

    protected function caseFacts(LegalCase $case, AnonymizationContext $ctx): string
    {
        $m = $ctx->masker;
        $lines = [
            "Ish: {$m->mask($case->title)}".($case->court_case_number ? ' (sud ishi № '.$ctx->identifier('ISH', 'case_number', $case->court_case_number).')' : ''),
            "Da'vogar (mijoz): {$m->mask($case->client_name)}",
        ];
        foreach ((array) $case->parties as $p) {
            if (is_array($p) && ! empty($p['name'])) {
                $lines[] = ucfirst((string) ($p['role'] ?? 'Taraf')).': '.$m->mask($p['name']);
            }
        }
        if ($case->description) {
            $lines[] = 'Ish tavsifi: '.$m->mask(mb_substr($case->description, 0, 2000));
        }
        foreach ($case->legalBases as $b) {
            $lines[] = 'Huquqiy asos: '.$this->basisLine($b, $ctx);
        }

        return implode("\n", $lines);
    }

    protected function basisText(DataRequest $r, AnonymizationContext $ctx): string
    {
        return $r->legalBasis ? $this->basisLine($r->legalBasis, $ctx) : '[huquqiy asos koʻrsatilmagan]';
    }

    /** "Sud qarori / ajrimi № [QAROR_1] (10.11.2026), [SUD_1]" — number and court are restored only locally. */
    protected function basisLine($b, AnonymizationContext $ctx): string
    {
        $number = $b->number ? $ctx->identifier($b->type === LegalBasisType::CourtDecision ? 'QAROR' : 'ORDER', 'decision_number', $b->number) : '[___]';
        $date = $b->issued_at ? ' ('.$b->issued_at->format('d.m.Y').')' : '';
        $court = $b->court_name ? ', '.$ctx->identifier('SUD', 'court_name', $b->court_name) : '';

        return trim("{$b->type?->label()} № {$number}{$date}{$court}");
    }

    protected function periodText(array $params): string
    {
        $from = $params['period_from'] ?? $params['from'] ?? null;
        $to = $params['period_to'] ?? $params['to'] ?? null;

        return $from || $to ? trim(($from ?? '…').' — '.($to ?? '…')) : 'koʻrsatilmagan';
    }

    protected function subjectPinflPlaceholder(AnonymizationContext $ctx, DataRequest $r): string
    {
        return $ctx->pinfl($r->subject_pinfl) ?? ($r->subject_pinfl_masked ? $ctx->masker->mask($r->subject_pinfl_masked) : '[___]');
    }

    protected function advocateFor(DataRequest $r): ?User
    {
        return $r->signer ?? ($r->creator?->isAdvocate() ? $r->creator : $r->legalCase?->owner);
    }

    protected function partyName(LegalCase $case, array $roles): ?string
    {
        foreach ((array) $case->parties as $p) {
            if (is_array($p) && in_array(mb_strtolower((string) ($p['role'] ?? '')), $roles, true) && ! empty($p['name'])) {
                return $p['name'];
            }
        }

        return null;
    }

    protected function dataRequested(DataRequestType $type): string
    {
        return match ($type) {
            DataRequestType::Salary => 'shaxsning ish haqi va boshqa daromadlari, ish beruvchilari hamda pensiya badallari toʻgʻrisidagi maʼlumotlar (oylar kesimida)',
            DataRequestType::Property => 'shaxsga tegishli koʻchmas mulk obyektlari (kadastr raqami, turi, maydoni, ulushi, taqiq va garovlar) toʻgʻrisidagi maʼlumotlar',
            DataRequestType::Vehicle => 'shaxs nomida roʻyxatda turgan avtotransport vositalari (rusumi, yili, davlat raqami, garov holati) toʻgʻrisidagi maʼlumotlar',
            DataRequestType::CourtCase => 'sud ishining holati, taraflari va qabul qilingan sud hujjatlari toʻgʻrisidagi maʼlumotlar',
            DataRequestType::TaxInfo => 'soliq toʻlovchining STIR, soliq rejimi, QQS toʻlovchi sifatida hisobda turishi, toʻlangan soliqlar va soliq qarzi summalari, YaTT holati va yuridik shaxslardagi ishtiroki (Soliq kodeksi 29-moddasiga koʻra soliq siri hisoblanmaydigan) toʻgʻrisidagi maʼlumotlar',
            DataRequestType::BusinessShares => 'shaxs ishtirokchi boʻlgan yuridik shaxslar (nomi, STIR, ustav fondidagi ulushi, faoliyat holati) toʻgʻrisidagi maʼlumotlar',
            DataRequestType::BankAccounts => 'shaxsning banklardagi hisobvaraqlari, qoldiqlari, davr boʻyicha aylanmalari va hisobvaraqlarga qoʻyilgan cheklovlar toʻgʻrisidagi maʼlumotlar (bank siri)',
            DataRequestType::BorderCrossing => 'shaxsning Oʻzbekiston Respublikasi davlat chegarasidan oʻtganligi (chiqish/kirish sanalari, davlat, oʻtish punkti) toʻgʻrisidagi maʼlumotlar',
        };
    }

    protected function requestAttachments(DataRequest $r): string
    {
        $items = ['advokat orderining koʻchirma nusxasi'];
        if ($r->legalBasis?->type?->value === 'court_decision') {
            $items[] = 'sud qarori (ajrimi) nusxasi';
        }

        return implode('; ', $items);
    }

    /** @return array{0: string, 1: list<array{0: string, 1: string}>, 2: string} */
    protected function assetMeaning(DataRequestType $type): array
    {
        return match ($type) {
            DataRequestType::Property => [
                'qaysi koʻchmas mulkka undiruv qaratilishi yoki daʼvoni taʼminlash tartibida xatlov (taqiq) qoʻyilishi mumkinligini baholang; garov/taqiq mavjud obyektlar va yagona turar joy boʻlishi mumkin boʻlgan obyektlarga alohida eʼtibor bering.',
                [['fuqarolik-protsessual-kodeksi', '105'], ['fuqarolik-protsessual-kodeksi', '106']],
                'koʻchmas mulkka undiruv qaratish ijro xatlov',
            ],
            DataRequestType::Vehicle => [
                'qaysi avtotransport vositasiga undiruv qaratilishi yoki daʼvoni taʼminlash choralari (xatlov, roʻyxatdan chiqarishni taqiqlash) qoʻllanishi mumkinligini baholang; garovdagi vositalarni alohida koʻrsating.',
                [['fuqarolik-protsessual-kodeksi', '105'], ['fuqarolik-protsessual-kodeksi', '106']],
                'transport vositasi mol-mulkka xatlov qoʻyish undiruv',
            ],
            DataRequestType::CourtCase => [
                'ishning bosqichi, qabul qilingan hujjatlar va advokat uchun protsessual muddatlar (shikoyat, ijro varaqasi) nimani anglatishini tushuntiring.',
                [['fuqarolik-protsessual-kodeksi', '451']],
                'sud hal qiluv qarori shikoyat muddati ijro',
            ],
            DataRequestType::TaxInfo => [
                'soliq qarzi, YaTT faoliyati (qoʻshimcha daromad manbai) va yuridik shaxslardagi ishtirok aliment/qarz undiruvi uchun nimani anglatishini baholang.',
                [['soliq-kodeksi', '29']],
                'daromad undiruv qaratish',
            ],
            DataRequestType::BusinessShares => [
                'yuridik shaxslardagi ulush undiruv qaratilishi mumkin boʻlgan mol-mulk ekanini va daʼvoni taʼminlash tartibida ulushga xatlov qoʻyish imkoniyatini baholang.',
                [['fuqarolik-protsessual-kodeksi', '105'], ['soliq-kodeksi', '29']],
                'ulushga undiruv qaratish xatlov',
            ],
            DataRequestType::BankAccounts => [
                'hisobvaraqlardagi mablagʻlarga undiruv qaratish yoki daʼvoni taʼminlash tartibida xatlov qoʻyish imkoniyatini baholang; mavjud cheklovlarni koʻrsating. Maʼlumot bank siri — faqat ish maqsadida foydalaning.',
                [['bank-siri', '10'], ['fuqarolik-protsessual-kodeksi', '105']],
                'pul mablagʻlariga xatlov qoʻyish',
            ],
            DataRequestType::BorderCrossing => [
                'qarzdorning chet elda boʻlishi aliment/qarz ijrosiga qanday taʼsir qilishini va ijro ishi doirasida chet elga chiqishni vaqtincha cheklash masalasini koʻrib chiqish mumkinligini taklif sifatida baholang.',
                [['shaxsga-doir-malumotlar', '25']],
                'chet elga chiqishni vaqtincha cheklash ijro',
            ],
            DataRequestType::Salary => ['', [], ''],
        };
    }

    protected function assetFacts(DataRequestType $type, array $data): array
    {
        return match ($type) {
            DataRequestType::Property => [
                'count' => count($data['objects'] ?? []),
                'encumbered' => collect($data['objects'] ?? [])->filter(fn ($o) => ! empty($o['encumbrances']))->count(),
                'items' => collect($data['objects'] ?? [])->map(fn ($o) => [
                    'label' => trim(($o['type'] ?? 'Obyekt').(isset($o['area']) ? ", {$o['area']} m²" : '').(isset($o['share']) ? ", ulush {$o['share']}" : '')),
                    'id' => $o['cadastral_number'] ?? null,
                    'encumbrances' => $o['encumbrances'] ?? [],
                ])->all(),
            ],
            DataRequestType::Vehicle => [
                'count' => count($data['vehicles'] ?? []),
                'encumbered' => collect($data['vehicles'] ?? [])->filter(fn ($v) => ! empty($v['pledge']))->count(),
                'items' => collect($data['vehicles'] ?? [])->map(fn ($v) => [
                    'label' => trim(($v['model'] ?? 'Transport').(isset($v['year']) ? ", {$v['year']}" : '')),
                    'id' => $v['plate'] ?? null,
                    'encumbrances' => ! empty($v['pledge']) ? [is_string($v['pledge']) ? $v['pledge'] : 'garov'] : [],
                ])->all(),
            ],
            DataRequestType::BankAccounts => [
                'count' => count($data['accounts'] ?? []),
                'encumbered' => collect($data['accounts'] ?? [])->filter(fn ($a) => ! empty($a['restrictions']))->count(),
            ],
            DataRequestType::BusinessShares => ['count' => count($data['companies'] ?? [])],
            DataRequestType::BorderCrossing => ['count' => count($data['crossings'] ?? [])],
            default => ['count' => $data === [] ? 0 : 1],
        };
    }

    protected function claimAttachments(Collection $completed, AnonymizationContext $ctx): array
    {
        $items = [
            'Daʼvo arizasining javobgar uchun nusxasi',
            'Bola(lar)ning tugʻilganlik toʻgʻrisidagi guvohnomasi nusxasi',
            'Nikoh (nikohdan ajratish) toʻgʻrisidagi guvohnoma nusxasi',
            'Advokat orderi',
        ];
        foreach ($completed as $r) {
            // the request id is a restorable placeholder: the AI never sees it, the advocate does
            $items[] = "{$r->type->label()} boʻyicha {$r->type->agency()} javobi (LexLink soʻrovi № ".$ctx->masker->alias('SOROV', (string) $r->id)
                .($r->completed_at ? ', '.$r->completed_at->format('d.m.Y') : '').')';
        }
        $items[] = 'Davlat boji boʻyicha hujjat (agar talab qilinsa)';

        return $items;
    }

    protected function calcForPrompt(array $calc): array
    {
        return [
            'davr' => $calc['period'],
            'oylar_soni' => $calc['months_count'],
            'jami_daromad' => $calc['total_income'],
            'ortacha_oylik' => $calc['average_monthly'],
            'bolalar_soni' => $calc['children'],
            'eng_kam_ish_haqi' => $calc['min_wage'],
            'bir_bola_uchun_eng_kam_aliment' => $calc['min_per_child'],
            'variantlar' => array_map(fn ($o) => ['bolalar' => $o['label'], 'ulush' => $o['share'], 'ulush_boyicha' => $o['by_share'], 'aliment' => $o['amount'], 'eng_kam_qollandi' => $o['floor_applied']], $calc['options']),
            'tanlangan' => $calc['selected'] ? ['bolalar' => $calc['selected']['children'], 'ulush' => $calc['selected']['share'], 'aliment' => $calc['selected']['amount']] : null,
        ];
    }

    protected function json(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';

        return mb_strlen($json) > 8000 ? mb_substr($json, 0, 8000).' …' : $json;
    }

    protected static function money(int $v): string
    {
        return number_format($v, 0, ',', ' ').' soʻm';
    }

    // ------------------------------------------------------------------ deterministic templates (fallback)

    protected function refList(iterable $sources): string
    {
        $out = [];
        $i = 0;
        foreach ($sources as $s) {
            $i++;
            $c = $this->kb->citation($s);
            $out[] = "[{$i}] {$c['law']}, {$c['article']}";
        }

        return implode('; ', $out);
    }

    protected function fallbackRequestText(array $v, DataRequestType $type, Collection $sources): string
    {
        $agency = $type->agency();
        $refs = $this->refList($sources);
        $secrecy = match ($type) {
            DataRequestType::Salary => "\nSoʻralayotgan maʼlumotlar soliq sirini tashkil etishi sababli soʻrov sud qarori asosida yuborilmoqda (Soliq kodeksi 29-modda).",
            DataRequestType::BankAccounts => "\nSoʻralayotgan maʼlumotlar bank sirini tashkil etishi sababli soʻrov sud ajrimi asosida yuborilmoqda (Bank siri toʻgʻrisidagi Qonun 10-modda).",
            DataRequestType::BorderCrossing => "\nSoʻralayotgan maʼlumotlar shaxsiy hayotga oid maxsus maʼlumot boʻlgani sababli soʻrov sud ajrimi asosida yuborilmoqda (Shaxsga doir maʼlumotlar toʻgʻrisidagi Qonun 25-modda).",
            DataRequestType::TaxInfo, DataRequestType::BusinessShares => "\nSoʻralayotgan maʼlumotlar Soliq kodeksi 29-moddasiga koʻra soliq sirini tashkil etmaydi.",
            default => '',
        };

        $refsLine = trim((string) $refs) !== '' ? "Huquqiy asoslar: {$refs}.\n\n" : '';

        return <<<TXT
        {$agency}ga

        ADVOKAT SOʻROVI

        Men, advokat {$v['advocate']}, {$v['client']} manfaatlarini koʻzlab, {$v['basis']} asosida malakali yuridik yordam koʻrsatish maqsadida murojaat qilaman.

        Advokatura toʻgʻrisidagi Qonunning 6 va 7¹-moddalari hamda Shaxsga doir maʼlumotlar toʻgʻrisidagi Qonunning 18-moddasiga muvofiq, quyidagi maʼlumotlarni taqdim etishingizni soʻrayman:
        — {$v['data_requested']};
        — shaxs: {$v['subject']} (JShShIR: {$v['subject_pinfl']});
        — davr: {$v['period']}.{$secrecy}

        Advokatura toʻgʻrisidagi Qonunning 7¹-moddasiga koʻra axborot soʻrov olingan sanadan eʼtiboran oʻn besh kundan kechiktirmay taqdim etiladi. Javobni elektron shaklda LexLink axborot tizimi orqali yuborishingizni soʻrayman.

        {$refsLine}Ilova: {$v['attachments']}.

        Advokat {$v['advocate']} (litsenziya № {$v['license']}) — ERI bilan imzolanadi.
        TXT;
    }

    protected function fallbackSalarySummary(array $calc, array $data, Collection $sources): string
    {
        $employers = count($data['employers'] ?? []);
        $rows = array_map(
            fn ($o) => "| {$o['label']} | {$o['share']} | ".self::money($o['amount']).($o['floor_applied'] ? ' *' : '').' |',
            $calc['options'],
        );
        $selected = $calc['selected']
            ? "\n\nIshda **{$calc['selected']['children']} nafar bola** koʻrsatilgan: taxminiy aliment — **".self::money($calc['selected']['amount'])."** oyiga ({$calc['selected']['share']} ulush) [1]."
            : "\n\nBolalar soni ishda koʻrsatilmagan — jadvaldagi mos variantni tanlang [1].";
        $floorNote = collect($calc['options'])->contains('floor_applied', true)
            ? "\n\n\\* Har bir bola uchun aliment eng kam ish haqining 26,5 foizidan (".self::money($calc['min_per_child']).') kam boʻlmasligi kerak [1].'
            : '';

        return "**Xulosa.** {$calc['period']['from']} — {$calc['period']['to']} davrida {$calc['months_count']} oy uchun daromad maʼlumoti olindi"
            .($employers ? " ({$employers} ta ish beruvchi)" : '').'. Jami: **'.self::money($calc['total_income']).'**, oʻrtacha oylik daromad: **'.self::money($calc['average_monthly']).'**.'
            ."\n\n**Aliment (Oila kodeksi 99-modda) [1]**\n\n| Bolalar | Ulush | Oyiga |\n|---|---|---|\n".implode("\n", $rows)
            .$selected.$floorNote
            ."\n\nAliment barcha turdagi daromadlardan ushlab qolinadi [2]. Keyingi qadam: aliment undirish toʻgʻrisida daʼvo arizasi loyihasini tayyorlash.";
    }

    protected function fallbackAssetSummary(DataRequestType $type, array $data, Collection $sources): string
    {
        $facts = $this->assetFacts($type, $data);

        if ($type === DataRequestType::CourtCase) {
            $status = $data['status'] ?? $data['stage'] ?? null;

            return $data === []
                ? '**Xulosa.** Soʻralgan sud ishi boʻyicha maʼlumot topilmadi.'
                : '**Xulosa.** Sud ishi maʼlumotlari olindi'.($status ? " (holati: {$status})" : '').'. Ish hujjatlari va protsessual muddatlarni tekshiring; ijro varaqasini ijroga taqdim etish muddatlari [1] da belgilangan.';
        }

        $noun = $type === DataRequestType::Property ? 'koʻchmas mulk obyekti' : 'avtotransport vositasi';
        if ($facts['count'] === 0) {
            return "**Xulosa.** Subyekt nomida roʻyxatdan oʻtgan {$noun} topilmadi. Undiruvni ish haqi va boshqa daromadlarga qaratish imkoniyatini koʻrib chiqing.";
        }

        $list = implode("\n", array_map(fn ($i) => '- '.$i['label'].($i['id'] ? " ({$i['id']})" : '').($i['encumbrances'] ? ' — cheklov: '.implode(', ', array_map(fn ($e) => is_array($e) ? implode(' ', $e) : $e, $i['encumbrances'])) : ''), $facts['items']));
        $free = $facts['count'] - $facts['encumbered'];

        return "**Xulosa.** {$facts['count']} ta {$noun} aniqlandi, shundan {$facts['encumbered']} tasida garov/taqiq bor.\n\n{$list}\n\n"
            ."**Ish uchun ahamiyati.** Cheklovsiz {$free} ta obyektga daʼvoni taʼminlash tartibida xatlov qoʻyishni soʻrash mumkin [1][2]; garovdagi obyektlar boʻyicha garovga oluvchining huquqlarini hisobga oling."
            .($type === DataRequestType::Property ? ' Yagona turar joy boʻlishi mumkin boʻlgan obyektga undiruv qaratish cheklanishi mumkin — tekshiring.' : '')
            ."\n\n**Keyingi qadam.** Daʼvo arizasida daʼvoni taʼminlash chorasi sifatida ushbu mol-mulkka xatlov qoʻyishni soʻrang.";
    }

    /** Types that reveal assets the claim may ask to attach (undiruv / da'voni ta'minlash). */
    protected const ASSET_TYPES = [DataRequestType::Property, DataRequestType::Vehicle, DataRequestType::BankAccounts, DataRequestType::BusinessShares];

    protected function secrecyHint(DataRequestType $type): string
    {
        return match ($type) {
            DataRequestType::Salary => ', soliq siri rejimi va sud qarori asosida berish',
            DataRequestType::BankAccounts => ', bank siri rejimi va sud ajrimi asosida berish (Bank siri toʻgʻrisidagi Qonun 10-modda)',
            DataRequestType::BorderCrossing => ', shaxsiy hayotga oid maxsus maʼlumot va sud ajrimi asosida berish',
            default => '',
        };
    }

    /**
     * PHP-computed figures for the domain types (ResponseSummarizer) — never computed by the model.
     * Prefers the stored summary (survives purge).
     */
    protected function domainNumbers(DataRequest $request, array $data): ?array
    {
        if (! in_array($request->type, [DataRequestType::TaxInfo, DataRequestType::BusinessShares, DataRequestType::BankAccounts, DataRequestType::BorderCrossing], true)) {
            return null;
        }
        $summary = (array) ($request->response?->summary ?? []);
        if (($summary['type'] ?? null) === $request->type->value) {
            return $summary;
        }

        return app(ResponseSummarizer::class)->summarize($request->type, ['data' => $data]);
    }

    protected function fallbackDomainSummary(DataRequestType $type, array $n, array $data): string
    {
        $tail = "\n\n_Bu tahlil taklif xarakterida; yakuniy huquqiy baho advokat zimmasida._";

        return match ($type) {
            DataRequestType::BankAccounts => $this->bankSummary($n).$tail,
            DataRequestType::BorderCrossing => $this->borderSummary($n).$tail,
            DataRequestType::BusinessShares => ($n['companies_count'] ?? 0) === 0
                ? '**Xulosa.** Subyekt yuridik shaxslarda ishtirokchi sifatida qayd etilmagan.'.$tail
                : "**Xulosa.** Subyekt {$n['companies_count']} ta yuridik shaxsda ishtirokchi (faol: {$n['active_count']}). Ulushlarning nominal qiymati jami: **".self::money((int) $n['total_share_value']).'** (ustav fondi × ulush, PHPda hisoblangan).'
                    ."\n\n**Ish uchun ahamiyati.** Yuridik shaxsdagi ulush undiruv qaratilishi mumkin boʻlgan mol-mulk hisoblanadi — daʼvoni taʼminlash tartibida ulushga xatlov qoʻyishni soʻrash masalasini koʻrib chiqish mumkin.".$tail,
            DataRequestType::TaxInfo => '**Xulosa.** Soliq rejimi: '.($n['tax_regime'] ?? '—').'; QQS toʻlovchi: '.(! empty($n['vat_payer']) ? 'ha' : 'yoʻq').'; YaTT: '.(! empty($n['is_individual_entrepreneur']) ? 'ha'.(! empty($n['ie_activity']) ? " ({$n['ie_activity']})" : '') : 'yoʻq').'.'
                ."\nSoliq qarzi: **".self::money((int) ($n['tax_debt'] ?? 0)).'**; oʻtgan yili toʻlangan soliqlar: '.self::money((int) ($n['taxes_paid_last_year'] ?? 0)).'; yuridik shaxslardagi ishtirok: '.((int) ($n['participations_count'] ?? 0)).' ta.'
                .(! empty($n['participations_count']) ? "\n\n**Keyingi qadam.** Ulushlar boʻyicha batafsil maʼlumot uchun «Yuridik shaxslardagi ulushlar» soʻrovini yuborish mumkin." : '')
                .(! empty($n['is_individual_entrepreneur']) ? "\n\nYaTT faoliyati qoʻshimcha daromad manbai boʻlishi mumkin — aliment hisobida inobatga olishni koʻrib chiqing." : '').$tail,
            default => '',
        };
    }

    protected function bankSummary(array $n): string
    {
        if (($n['accounts_count'] ?? 0) === 0) {
            return '**Xulosa.** Subyekt nomida bank hisobvaragʻi topilmadi.';
        }
        $totals = collect($n['totals_by_currency'] ?? [])->map(fn ($t, $cur) => ($cur === 'UZS' ? self::money((int) $t['balance']) : number_format((int) $t['balance'], 0, ',', ' ')." {$cur}")." ({$t['accounts']} ta hisobvaraq; {$n['turnover_months']} oyda kirim ".number_format((int) $t['turnover_in'], 0, ',', ' ').')')->values()->implode('; ');

        return "**Xulosa.** {$n['banks_count']} ta bankda {$n['accounts_count']} ta hisobvaraq aniqlandi. Qoldiq valyutalar boʻyicha: **{$totals}** (PHPda hisoblangan)."
            .(($n['restricted_count'] ?? 0) > 0 ? " {$n['restricted_count']} ta hisobvaraqqa allaqachon cheklov qoʻyilgan." : '')
            ."\n\n**Ish uchun ahamiyati.** Hisobvaraqlardagi mablagʻlarga daʼvoni taʼminlash tartibida xatlov qoʻyish yoki ijro ishi doirasida undiruv qaratish masalasini koʻrib chiqish mumkin. Maʼlumot bank siri — faqat ushbu ish doirasida foydalaning.";
    }

    protected function borderSummary(array $n): string
    {
        if (($n['crossings_count'] ?? 0) === 0) {
            return '**Xulosa.** Koʻrsatilgan davrda chegaradan oʻtish qayd etilmagan.';
        }
        $head = "**Xulosa.** {$n['crossings_count']} ta chegaradan oʻtish qayd etilgan; soʻnggi 12 oyda chet elda jami **{$n['days_abroad_last_12m']} kun**.";
        if (! empty($n['currently_abroad'])) {
            return $head."\n\nQarzdor **{$n['last_exit_date']} dan beri chet elda ({$n['last_exit_country']})** — {$n['days_abroad_current']} kun, qaytib kirgani qayd etilmagan."
                ."\n\n**Ish uchun ahamiyati.** Ijro ishi boʻyicha chet elga chiqishni vaqtincha cheklash masalasini koʻrib chiqish, shuningdek undiruvni mamlakatdagi mol-mulk va hisobvaraqlarga qaratish mumkin.";
        }

        return $head.' Hozirda subyekt mamlakatda (soʻnggi qayd — kirish).';
    }

    /** PHP numbers about assets for the claim prompt. */
    protected function claimAssetNumbers(Collection $completed): array
    {
        $out = [];
        foreach ($completed as $r) {
            if (in_array($r->type, [DataRequestType::BankAccounts, DataRequestType::BusinessShares, DataRequestType::BorderCrossing], true)) {
                $out['aktivlar_'.$r->type->value] = $this->domainNumbers($r, $this->responseData($r));
            }
        }

        return $out;
    }

    protected function claimAssetLine(Collection $completed): string
    {
        if (! $completed->contains(fn ($r) => in_array($r->type, self::ASSET_TYPES, true))) {
            return '';
        }
        $what = [];
        foreach ($completed as $r) {
            $n = $this->domainNumbers($r, $this->responseData($r)) ?? [];
            if ($r->type === DataRequestType::BankAccounts && ($n['accounts_count'] ?? 0) > 0) {
                $uzs = (int) ($n['totals_by_currency']['UZS']['balance'] ?? 0);
                $what[] = "bank hisobvaraqlaridagi pul mablagʻlari ({$n['accounts_count']} ta hisobvaraq".($uzs ? ', qoldiq '.self::money($uzs) : '').')';
            } elseif ($r->type === DataRequestType::BusinessShares && ($n['companies_count'] ?? 0) > 0) {
                $what[] = 'yuridik shaxslardagi ulushlar (nominal qiymati '.self::money((int) $n['total_share_value']).')';
            } elseif (in_array($r->type, [DataRequestType::Property, DataRequestType::Vehicle], true)) {
                $what[] = $r->type === DataRequestType::Property ? 'koʻchmas mulk' : 'avtotransport vositalari';
            }
        }

        return "\n4. Daʼvoni taʼminlash maqsadida javobgarga tegishli mol-mulkka".($what ? ' — '.implode(', ', array_unique($what)) : '').' — xatlov qoʻyish (FPK).';
    }

    protected function fallbackClaim(array $v, ?array $calc, ?int $children, Collection $completed, Collection $sources): string
    {
        $income = $calc
            ? "Javobgarning {$calc['period']['from']} — {$calc['period']['to']} davridagi ({$calc['months_count']} oy) oʻrtacha oylik daromadi ".self::money($calc['average_monthly']).' ni tashkil etadi (davlat idorasining rasmiy javobi asosida).'
            : 'Javobgarning daromadi toʻgʻrisidagi maʼlumot [___].';
        $sel = $calc && $children ? ($calc['selected'] ?? null) : null;
        $amount = $sel
            ? "{$sel['share']} qismi miqdorida (taxminan oyiga ".self::money($sel['amount']).')'
            : '[___] qismi miqdorida';
        $assets = $this->claimAssetLine($completed);
        $attachments = implode("\n", array_map(fn ($a, $i) => ($i + 1).". {$a}", explode('; ', $v['attachments']), array_keys(explode('; ', $v['attachments']))));
        $refs = $this->refList($sources);
        $date = now()->format('d.m.Y');

        return <<<TXT
        **{$v['court']}ga**

        **Daʼvogar:** {$v['claimant']}, manzil: [___]
        **Javobgar:** {$v['defendant']} (JShShIR: {$v['defendant_pinfl']}), manzil: [___]
        **Daʼvogar vakili:** advokat {$v['advocate']}
        **Daʼvo bahosi:** aliment, oyiga {$amount}

        ## DAʼVO ARIZASI
        ### (voyaga yetmagan bola(lar) taʼminoti uchun aliment undirish toʻgʻrisida)

        ### Daʼvo mazmuni

        Daʼvogar va javobgarning {$v['children_text']} voyaga yetmagan farzandi bor. Javobgar bolalar taʼminoti uchun ixtiyoriy ravishda mablagʻ bermayapti, aliment toʻlash toʻgʻrisida kelishuv tuzilmagan.

        {$income}

        ### Huquqiy asoslar

        Oila kodeksining 98 va 99-moddalariga koʻra, kelishuv boʻlmasa, voyaga yetmagan bolalar taʼminoti uchun aliment sud tomonidan ota-onaning har oydagi ish haqi va (yoki) boshqa daromadining bir bola uchun — toʻrtdan bir, ikki bola uchun — uchdan bir, uch va undan ortiq bola uchun — yarmi miqdorida undiriladi. Oila kodeksining 104-moddasiga koʻra aliment barcha turdagi daromadlardan ushlab qolinadi. Ariza FPKning 189-moddasi talablariga muvofiq tuzildi; FPKning 34-moddasiga koʻra aliment undirish toʻgʻrisidagi daʼvo daʼvogar yashab turgan joydagi sudga ham taqdim etilishi mumkin.

        Manbalar: {$refs}.

        ### Yuqoridagilarga asosan, SOʻRAYMAN:

        1. Javobgar {$v['defendant']}dan voyaga yetmagan bola(lar) taʼminoti uchun har oydagi ish haqi va boshqa daromadining {$amount} aliment undirilsin.
        2. Aliment daʼvo arizasi sudga berilgan kundan boshlab, bola(lar) voyaga yetgunga qadar undirilsin.
        3. Ish mazmunan hal qilingunga qadar vaqtincha aliment undirish toʻgʻrisida ajrim chiqarilsin (FPK 187-modda, Oila kodeksi 112-modda).{$assets}

        ### Ilovalar

        {$attachments}

        Sana: {$date}

        Daʼvogar vakili, advokat {$v['advocate']} ______
        TXT;
    }

    protected function fallbackAnswer(Collection $sources): string
    {
        if ($sources->isEmpty()) {
            return 'Bilimlar bazasida savolingizga mos modda topilmadi. Savolni aniqroq (masalan, qonun nomi yoki tushuncha bilan) yozib koʻring.';
        }

        $lines = ['Bilimlar bazasidan savolingizga eng mos moddalar (AI generatsiyasi oʻchirilgan — matn qonundan aynan keltirildi):'];
        foreach ($sources->take(4)->values() as $i => $chunk) {
            $c = $this->kb->citation($chunk);
            $body = trim(preg_replace('/^[^\n]*\n/u', '', $chunk->content, 1) ?? $chunk->content);
            $excerpt = mb_strlen($body) > 600 ? mb_substr($body, 0, 600).' …' : $body;
            $lines[] = '**['.($i + 1)."] {$c['law']}, {$c['article']}. {$c['title']}**\n\n> ".str_replace("\n", "\n> ", $excerpt);
        }

        return implode("\n\n", $lines);
    }
}

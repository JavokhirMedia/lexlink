<?php

namespace App\Services\Ai;

use App\Enums\AuditAction;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Models\AiAnalysis;
use App\Models\DataRequest;
use App\Models\LegalCase;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BillingService;
use Illuminate\Support\Collection;

/**
 * Shared pipeline for every AI operation:
 *   1. build facts from the case/request and MASK all PII (PiiMasker),
 *   2. compute numbers in PHP (AlimonyCalculator),
 *   3. retrieve lex.uz articles (deterministic legal refs + RAG search),
 *   4. render a versioned prompt (resources/prompts) and complete() it — Gemini or a deterministic template,
 *   5. persist AiAnalysis (masked output + display map), audit, return.
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
    ) {}

    /**
     * Generate text for an already MASKED prompt. Must return the deterministic $fallback when generation is unavailable.
     *
     * @return array{text: string, tokens: int, model: string}
     */
    abstract protected function complete(string $prompt, string $fallback, PiiMasker $masker, array $options = []): array;

    // ------------------------------------------------------------------ AiService

    public function draftRequestText(DataRequest $request): string
    {
        $request->loadMissing(['legalCase.owner.advocateProfile', 'legalBasis', 'creator']);
        $case = $request->legalCase;
        $masker = $this->maskerFor($case, $request);
        $type = $request->type;

        $refs = [['advokatura', '7-1'], ['advokatura', '6'], ['shaxsga-doir-malumotlar', '18'], ['advokatlik-kafolatlari', '4']];
        if ($type === DataRequestType::Salary) {
            $refs[] = ['soliq-kodeksi', '29'];
        }
        $sources = $this->sources($refs, null);

        $advocate = $this->advocateFor($request);
        $vars = [
            'advocate' => $masker->mask($advocate?->name ?? '[___]'),
            'client' => $masker->mask($case->client_name),
            'basis' => $this->basisText($request),
            'data_requested' => $this->dataRequested($type),
            'period' => $this->periodText($request->params ?? []),
            'subject' => $masker->mask($request->subject_name ?: '[___]'),
            'subject_pinfl' => $this->subjectPinflPlaceholder($masker, $request),
            'secrecy_hint' => $type === DataRequestType::Salary ? ', soliq siri rejimi va sud qarori asosida berish' : '',
            'attachments' => $this->requestAttachments($request),
            'license' => $advocate?->advocateProfile?->license_number ?? '[___]',
            'facts' => $this->requestFacts($request, $masker),
            'sources' => $this->kb->context($sources),
        ];

        [$prompt, $version] = $this->prompts->render('request_text', $vars);
        $result = $this->complete($prompt, $this->fallbackRequestText($vars, $type, $sources), $masker, ['max_tokens' => 2048]);

        $this->persist('request_text', $case, $request, $result, $masker, $version, $sources, ['type' => $type->value]);

        return $masker->unmask($result['text']);
    }

    public function analyzeResponse(DataRequest $request): AiAnalysis
    {
        $request->loadMissing(['legalCase.owner', 'legalBasis', 'response']);
        $case = $request->legalCase;
        $masker = $this->maskerFor($case, $request);
        $data = $this->responseData($request);
        $maskedData = $masker->maskArray($data);
        $facts = $this->requestFacts($request, $masker);

        if ($request->type === DataRequestType::Salary) {
            $children = AlimonyCalculator::detectChildren($request->params ?? [], $case->description, $request->purpose, $case->title);
            $calc = $this->calculator->calculate($data['months'] ?? [], $children);
            $sources = $this->sources(
                [['oila-kodeksi', '99'], ['oila-kodeksi', '104'], ['oila-kodeksi', '102'], ['mehnat-kodeksi', '270']],
                null,
            );
            [$prompt, $version] = $this->prompts->render('analyze_salary', [
                'facts' => $facts,
                'payload' => $this->json($this->trimSalaryPayload($maskedData)),
                'calculation' => $this->json($this->calcForPrompt($calc)),
                'sources' => $this->kb->context($sources),
            ]);
            $fallback = $this->fallbackSalarySummary($calc, $maskedData, $sources);
            $structured = ['type' => 'salary', 'alimony' => $calc, 'children_detected' => $children];
        } else {
            [$hint, $refs, $query] = $this->assetMeaning($request->type);
            $sources = $this->sources($refs, $query, 3);
            [$prompt, $version] = $this->prompts->render('analyze_asset', [
                'type_label' => $request->type->label(),
                'meaning_hint' => $hint,
                'facts' => $facts,
                'payload' => $this->json($maskedData),
                'sources' => $this->kb->context($sources),
            ]);
            $fallback = $this->fallbackAssetSummary($request->type, $maskedData, $sources);
            $structured = ['type' => $request->type->value, 'facts' => $this->assetFacts($request->type, $maskedData)];
        }

        $result = $this->complete($prompt, $fallback, $masker);
        $analysis = $this->persist('summary', $case, $request, $result, $masker, $version, $sources, $structured);
        $this->audit->log(AuditAction::AiAnalysis, $analysis, ['kind' => 'summary', 'request_id' => $request->id, 'model' => $analysis->model], $case->id);

        return $analysis;
    }

    public function draftClaim(LegalCase $case, array $options = []): AiAnalysis
    {
        $case->loadMissing(['owner.advocateProfile', 'dataRequests.response', 'dataRequests.legalBasis']);
        $user = $options['user'] ?? auth()->user();
        $masker = $this->maskerFor($case);

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
            'type' => $r->type->label(),
            'agency' => $r->type->agency(),
            'completed_at' => $r->completed_at?->toDateString(),
            'data' => $masker->maskArray($r->type === DataRequestType::Salary ? $this->trimSalaryPayload($this->responseData($r)) : $this->responseData($r)),
        ])->all();

        $sources = $this->sources(
            [['oila-kodeksi', '98'], ['oila-kodeksi', '99'], ['oila-kodeksi', '104'], ['oila-kodeksi', '112'], ['fuqarolik-protsessual-kodeksi', '189'], ['fuqarolik-protsessual-kodeksi', '34'], ['fuqarolik-protsessual-kodeksi', '187']],
            $completed->contains(fn ($r) => in_array($r->type, [DataRequestType::Property, DataRequestType::Vehicle], true)) ? "da'voni ta'minlash choralari mol-mulkka xatlov" : null,
            2,
        );

        $vars = [
            'court' => $options['court'] ?? "[___] tumanlararo fuqarolik ishlari bo'yicha sudi",
            'claimant' => $claimant,
            'defendant' => $defendant,
            'defendant_pinfl' => $salary ? $this->subjectPinflPlaceholder($masker, $salary) : '[___]',
            'advocate' => $masker->mask($case->owner?->name ?? '[___]'),
            'children_text' => $children ? "{$children} nafar" : 'bolalar soni koʻrsatilmagan — [___]',
            'attachments' => implode('; ', $this->claimAttachments($completed)),
            'facts' => $this->caseFacts($case, $masker),
            'responses' => $this->json($responses),
            'calculation' => $calc ? $this->json($this->calcForPrompt($calc)) : "Ish haqi ma'lumoti hali olinmagan.",
            'sources' => $this->kb->context($sources),
        ];

        [$prompt, $version] = $this->prompts->render('draft_claim', $vars);
        $fallback = $this->fallbackClaim($vars, $calc, $children, $completed, $sources);

        $cost = 0;
        if ($user && class_exists(BillingService::class) && ($options['charge'] ?? true)) {
            $cost = app(BillingService::class)->chargeAi($user, 'draft_claim'); // may throw InsufficientBalance
        }

        $result = $this->complete($prompt, $fallback, $masker, ['max_tokens' => 8192]);
        $analysis = $this->persist('draft_claim', $case, null, $result, $masker, $version, $sources, [
            'type' => 'draft_claim',
            'alimony' => $calc,
            'children' => $children,
            'responses_used' => $completed->pluck('id')->all(),
        ], $user, $cost);

        $this->audit->log(AuditAction::AiDraftClaim, $analysis, ['kind' => 'draft_claim', 'model' => $analysis->model, 'cost' => $cost], $case->id, $user);

        return $analysis;
    }

    public function askLaw(string $question): array
    {
        $masker = new PiiMasker;
        $masked = $masker->mask($question);
        $sources = $this->kb->search($masked, 6);

        [$prompt, $version] = $this->prompts->render('ask_law', [
            'question' => $masked,
            'sources' => $sources->isEmpty() ? '(manbalar topilmadi)' : $this->kb->context($sources),
        ]);
        $result = $this->complete($prompt, $this->fallbackAnswer($sources), $masker, ['max_tokens' => 4096]);

        return [
            'answer' => $masker->unmask($result['text']),
            'citations' => $this->kb->citations($sources),
            'model' => $result['model'],
            'tokens' => $result['tokens'],
            'prompt_version' => $version,
            'disclaimer' => (string) config('lexlink.ai.disclaimer'),
        ];
    }

    // ------------------------------------------------------------------ helpers

    /** Masker pre-loaded with every person known for the case (deterministic placeholders). */
    public function maskerFor(LegalCase $case, ?DataRequest $request = null): PiiMasker
    {
        $case->loadMissing(['owner', 'legalBases', 'dataRequests']);
        $m = new PiiMasker;
        $m->addName($case->owner?->name);
        $m->addName($case->client_name);
        foreach ((array) $case->parties as $party) {
            $m->addName(is_array($party) ? ($party['name'] ?? null) : null);
        }
        $requests = $request ? collect([$request])->merge($case->dataRequests->where('id', '!=', $request->id)) : $case->dataRequests;
        foreach ($requests as $r) {
            $m->addName($r->subject_name);
            $m->addPinfl($r->subject_pinfl);
        }
        foreach ($case->legalBases as $b) {
            $m->addName($b->subject_name);
            $m->addPinfl($b->subject_pinfl);
        }
        if ($request?->signer) {
            $m->addName($request->signer->name);
        }

        return $m;
    }

    /** Persist an analysis: output stays masked; structured._pii holds the display map (names, masked ids only). */
    protected function persist(string $kind, LegalCase $case, ?DataRequest $request, array $result, PiiMasker $masker, string $version, iterable $sources, array $structured, ?User $user = null, int $cost = 0): AiAnalysis
    {
        $structured['disclaimer'] = (string) config('lexlink.ai.disclaimer');
        $structured['generator'] = str_starts_with($result['model'], 'fallback') ? 'fallback' : 'gemini';
        $structured['_pii'] = $masker->displayMap();

        return AiAnalysis::create([
            'data_request_id' => $request?->id,
            'case_id' => $case->id,
            'created_by' => ($user ?? auth()->user())?->id,
            'kind' => $kind,
            'status' => 'completed',
            'model' => mb_substr($result['model'], 0, 64),
            'prompt_version' => $version,
            'output' => $result['text'],
            'structured' => $structured,
            'citations' => $this->kb->citations($sources),
            'tokens' => $result['tokens'],
            'cost' => $cost,
        ]);
    }

    /**
     * Deterministic legal refs first, then RAG hits for $query (deduplicated).
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

    protected function requestFacts(DataRequest $r, PiiMasker $m): string
    {
        $case = $r->legalCase;
        $lines = [
            "Ish: {$m->mask($case->title)}".($case->court_case_number ? " (sud ishi № {$case->court_case_number})" : '').($case->category ? ", toifa: {$case->category}" : ''),
            "Mijoz (advokat himoyasidagi shaxs): {$m->mask($case->client_name)}",
            "So'rov turi: {$r->type->label()}; idora: {$r->type->agency()}; rejim: {$r->type->secrecyRegime()}",
            'Subyekt: '.$m->mask($r->subject_name ?: '[___]').', JShShIR '.$this->subjectPinflPlaceholder($m, $r),
            'Davr: '.$this->periodText($r->params ?? []),
            'Huquqiy asos: '.$this->basisText($r),
        ];
        if ($r->purpose) {
            $lines[] = 'Maqsad: '.$m->mask($r->purpose);
        }
        if ($case->description) {
            $lines[] = 'Ish tavsifi: '.$m->mask(mb_substr($case->description, 0, 1500));
        }

        return implode("\n", $lines);
    }

    protected function caseFacts(LegalCase $case, PiiMasker $m): string
    {
        $lines = [
            "Ish: {$m->mask($case->title)}".($case->court_case_number ? " (sud ishi № {$case->court_case_number})" : ''),
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
            $lines[] = "Huquqiy asos: {$b->type?->label()} № {$b->number}".($b->court_name ? ", {$b->court_name}" : '').($b->issued_at ? ', '.$b->issued_at->toDateString() : '');
        }

        return implode("\n", $lines);
    }

    protected function basisText(DataRequest $r): string
    {
        $b = $r->legalBasis;
        if (! $b) {
            return '[huquqiy asos koʻrsatilmagan]';
        }

        return trim("{$b->type?->label()} № {$b->number}".($b->issued_at ? ' ('.$b->issued_at->format('d.m.Y').')' : '').($b->court_name ? ", {$b->court_name}" : ''));
    }

    protected function periodText(array $params): string
    {
        $from = $params['period_from'] ?? $params['from'] ?? null;
        $to = $params['period_to'] ?? $params['to'] ?? null;

        return $from || $to ? trim(($from ?? '…').' — '.($to ?? '…')) : 'koʻrsatilmagan';
    }

    protected function subjectPinflPlaceholder(PiiMasker $m, DataRequest $r): string
    {
        return $m->addPinfl($r->subject_pinfl) ?? ($r->subject_pinfl_masked ? $m->mask($r->subject_pinfl_masked) : '[___]');
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
            default => ['count' => $data === [] ? 0 : 1],
        };
    }

    protected function claimAttachments(Collection $completed): array
    {
        $items = [
            'Daʼvo arizasining javobgar uchun nusxasi',
            'Bola(lar)ning tugʻilganlik toʻgʻrisidagi guvohnomasi nusxasi',
            'Nikoh (nikohdan ajratish) toʻgʻrisidagi guvohnoma nusxasi',
            'Advokat orderi',
        ];
        foreach ($completed as $r) {
            $items[] = "{$r->type->label()} boʻyicha {$r->type->agency()} javobi (LexLink soʻrovi № {$r->id}".($r->completed_at ? ', '.$r->completed_at->format('d.m.Y') : '').')';
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

    protected function trimSalaryPayload(array $data): array
    {
        unset($data['months']); // per-month rows are summarised by the calculator; employers + totals are enough for the model

        return $data;
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
        $secrecy = $type === DataRequestType::Salary
            ? "\nSoʻralayotgan maʼlumotlar soliq sirini tashkil etishi sababli soʻrov sud qarori asosida yuborilmoqda (Soliq kodeksi 29-modda)."
            : '';

        return <<<TXT
        {$agency}ga

        ADVOKAT SOʻROVI

        Men, advokat {$v['advocate']}, {$v['client']} manfaatlarini koʻzlab, {$v['basis']} asosida malakali yuridik yordam koʻrsatish maqsadida murojaat qilaman.

        Advokatura toʻgʻrisidagi Qonunning 6 va 7¹-moddalari hamda Shaxsga doir maʼlumotlar toʻgʻrisidagi Qonunning 18-moddasiga muvofiq, quyidagi maʼlumotlarni taqdim etishingizni soʻrayman:
        — {$v['data_requested']};
        — shaxs: {$v['subject']} (JShShIR: {$v['subject_pinfl']});
        — davr: {$v['period']}.{$secrecy}

        Advokatura toʻgʻrisidagi Qonunning 7¹-moddasiga koʻra axborot soʻrov olingan sanadan eʼtiboran oʻn besh kundan kechiktirmay taqdim etiladi. Javobni elektron shaklda LexLink axborot tizimi orqali yuborishingizni soʻrayman.

        Huquqiy asoslar: {$refs}.

        Ilova: {$v['attachments']}.

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

    protected function fallbackClaim(array $v, ?array $calc, ?int $children, Collection $completed, Collection $sources): string
    {
        $income = $calc
            ? "Javobgarning {$calc['period']['from']} — {$calc['period']['to']} davridagi ({$calc['months_count']} oy) oʻrtacha oylik daromadi ".self::money($calc['average_monthly']).' ni tashkil etadi (davlat idorasining rasmiy javobi asosida).'
            : 'Javobgarning daromadi toʻgʻrisidagi maʼlumot [___].';
        $sel = $calc && $children ? ($calc['selected'] ?? null) : null;
        $amount = $sel
            ? "{$sel['share']} qismi miqdorida (taxminan oyiga ".self::money($sel['amount']).')'
            : '[___] qismi miqdorida';
        $assets = $completed->filter(fn ($r) => in_array($r->type, [DataRequestType::Property, DataRequestType::Vehicle], true))->isNotEmpty()
            ? "\n4. Daʼvoni taʼminlash maqsadida javobgarga tegishli mol-mulkka xatlov qoʻyish (FPK)."
            : '';
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

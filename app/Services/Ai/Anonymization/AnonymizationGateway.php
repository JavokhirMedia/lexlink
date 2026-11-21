<?php

namespace App\Services\Ai\Anonymization;

use App\Enums\AuditAction;
use App\Services\AuditLogger;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The ONLY path from LexLink to Gemini (servers outside Uzbekistan).
 *
 * Legal position: only egasizlantirilgan data leaves the server — after the projection + masking it is
 * impossible to determine that the data belongs to a specific subject (ShMQ 16-modda), so it is no longer
 * personal data and transchegaraviy uzatish rules (ShMQ 15-modda) are not triggered. ShMQ 27¹ (storage in
 * Uzbekistan) concerns biometric / genetic / telecom-user data only and is not relied on here.
 *
 * Event dates and amounts stay (the analysis needs them); identifiers and birth dates are masked.
 *
 * Pipeline per call: (1) final masking pass over every non-public part of the text, (2) LeakGuard scan against
 * the context's known-PII set + regex detectors, (3) on any hit: no HTTP call, audit AiPiiBlocked (counts only),
 * warning log without values, PiiLeakBlocked thrown so the caller returns its deterministic fallback;
 * (4) otherwise a sealed AnonymizedPayload + transparency report.
 */
final class AnonymizationGateway
{
    private static ?string $secret = null;

    public function __construct(private LeakGuard $guard, private AuditLogger $audit) {}

    /**
     * @param  list<string>  $publicSegments  verbatim lex.uz article text embedded in the prompt (public, not PII)
     *
     * @throws PiiLeakBlocked
     */
    public function generation(string $system, string $prompt, AnonymizationContext $ctx, array $publicSegments = []): AnonymizedPayload
    {
        $publicSegments = array_values(array_filter($publicSegments, fn ($s) => trim((string) $s) !== ''));
        $text = $this->maskOutsidePublic($prompt, $ctx, $publicSegments);

        $hits = $this->guard->scan($system, $ctx) + [];
        foreach ($this->guard->scan($text, $ctx, $publicSegments) as $cat => $n) {
            $hits[$cat] = ($hits[$cat] ?? 0) + $n;
        }
        $this->blockIfLeaking($hits, $ctx, $text, $publicSegments);

        $report = $this->report($text, $ctx, $publicSegments);

        return self::issue(AnonymizedPayload::KIND_GENERATION, $system, $text, [], $report);
    }

    /** Query embedding for RAG: the query is masked and scanned like a prompt. @throws PiiLeakBlocked */
    public function queryEmbedding(string $query, ?AnonymizationContext $ctx = null): AnonymizedPayload
    {
        $ctx ??= AnonymizationContext::public('query_embedding');
        $text = $ctx->masker->mask($query);
        $this->blockIfLeaking($this->guard->scan($text, $ctx), $ctx, $text, []);

        return self::issue(AnonymizedPayload::KIND_EMBEDDING, '', '', [$text], $this->report($text, $ctx, []));
    }

    /**
     * Public law text (lex.uz) for `lex:embed`: not personal data, sent verbatim, but still scanned with the
     * context-free detectors (no case context). @throws PiiLeakBlocked
     *
     * @param  list<string>  $texts
     */
    public function publicText(array $texts): AnonymizedPayload
    {
        $ctx = AnonymizationContext::public('lex_embed');
        $texts = array_values(array_map('strval', $texts));
        $hits = [];
        foreach ($texts as $t) {
            foreach ($this->guard->scan($t, null) as $cat => $n) {
                // law text legitimately contains dates / article numbers; only personal identifiers block it
                if (! in_array($cat, ['case_number', 'license', 'decision_number', 'request_number'], true)) {
                    $hits[$cat] = ($hits[$cat] ?? 0) + $n;
                }
            }
        }
        $this->blockIfLeaking($hits, $ctx, '', []);

        return self::issue(AnonymizedPayload::KIND_EMBEDDING, '', '', $texts, AnonymizationReport::make('passed', true, note: 'Ochiq qonun matni (lex.uz) — shaxsga doir maʼlumot emas.'));
    }

    /** GeminiClient calls this before every HTTP request. */
    public static function verify(AnonymizedPayload $payload): bool
    {
        return hash_equals(self::seal($payload->content()), $payload->seal);
    }

    // ------------------------------------------------------------------ internals

    /** Mask every part of $prompt except the verbatim public segments (law text must stay intact). */
    private function maskOutsidePublic(string $prompt, AnonymizationContext $ctx, array $publicSegments): string
    {
        $tokens = [];
        foreach ($publicSegments as $i => $seg) {
            $token = "\u{E000}PUBLIC{$i}\u{E001}";
            if (str_contains($prompt, $seg)) {
                $prompt = str_replace($seg, $token, $prompt);
                $tokens[$token] = $seg;
            }
        }
        $masked = $ctx->masker->mask($prompt);

        return $tokens === [] ? $masked : strtr($masked, $tokens);
    }

    private function report(string $text, AnonymizationContext $ctx, array $publicSegments): array
    {
        $scanned = $text;
        foreach ($publicSegments as $seg) {
            $scanned = str_replace($seg, '', $scanned);
        }

        return AnonymizationReport::make('passed', true, AnonymizationReport::countPlaceholders($scanned), $ctx->droppedFields(), $ctx->generalizedFields());
    }

    /** @param array<string, int> $hits */
    private function blockIfLeaking(array $hits, AnonymizationContext $ctx, string $text, array $publicSegments): void
    {
        $hits = array_filter($hits);
        if ($hits === []) {
            return;
        }
        ksort($hits);
        $meta = ['operation' => $ctx->operation, 'categories' => $hits, 'total' => array_sum($hits)];

        Log::warning('AI request blocked by the anonymisation leak guard', $meta);
        try {
            $this->audit->log(AuditAction::AiPiiBlocked, $ctx->subject, $meta, $ctx->caseId, $ctx->user);
        } catch (Throwable $e) {
            Log::error('Could not write AiPiiBlocked audit entry', ['error' => mb_substr($e->getMessage(), 0, 200)]);
        }

        $report = AnonymizationReport::make('blocked', false, [], $ctx->droppedFields(), $ctx->generalizedFields(), $hits,
            "AI so'rovi bloklandi: yuboriladigan matnda shaxsga doir ma'lumot aniqlandi. Gemini chaqirilmadi, javob mahalliy shablon asosida tayyorlandi.");

        throw new PiiLeakBlocked($hits, $report);
    }

    private static function issue(string $kind, string $system, string $text, array $texts, array $report): AnonymizedPayload
    {
        $seal = self::seal(AnonymizedPayload::canonical($kind, $system, $text, $texts));

        // AnonymizedPayload's constructor is private: only this scope-bound closure may call it.
        $factory = Closure::bind(
            static fn (...$args) => new AnonymizedPayload(...$args),
            null,
            AnonymizedPayload::class,
        );

        return $factory($kind, $system, $text, $texts, $report, $seal);
    }

    private static function seal(string $content): string
    {
        self::$secret ??= random_bytes(32);

        return hash_hmac('sha256', $content, self::$secret.config('app.key'));
    }
}

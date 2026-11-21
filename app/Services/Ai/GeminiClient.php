<?php

namespace App\Services\Ai;

use App\Services\Ai\Anonymization\AnonymizationGateway;
use App\Services\Ai\Anonymization\AnonymizedPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Gemini REST client (generativelanguage.googleapis.com v1beta).
 * The API key is sent in the `x-goog-api-key` header (never in the URL, so it never lands in logs).
 *
 * Gemini servers are outside Uzbekistan: generate()/embed()/embedQuery() accept ONLY an AnonymizedPayload,
 * which only Anonymization\AnonymizationGateway can issue (private constructor + HMAC seal re-checked here).
 * Passing a plain string is a TypeError; a forged / tampered payload is refused before any HTTP call.
 */
class GeminiClient
{
    public function enabled(): bool
    {
        return filled(config('lexlink.ai.gemini_key'));
    }

    public function model(): string
    {
        return (string) config('lexlink.ai.gemini_model');
    }

    public function embeddingModel(): string
    {
        return (string) config('lexlink.ai.gemini_embedding_model');
    }

    /**
     * @return array{text: string, tokens: int, model: string}
     */
    public function generate(AnonymizedPayload $payload, array $options = []): array
    {
        $this->assertIssued($payload, AnonymizedPayload::KIND_GENERATION);
        $model = $options['model'] ?? $this->model();

        $body = [
            'systemInstruction' => ['parts' => [['text' => $payload->system]]],
            'contents' => [['role' => 'user', 'parts' => [['text' => $payload->text]]]],
            'generationConfig' => array_filter([
                'temperature' => $options['temperature'] ?? config('lexlink.ai.temperature', 0.2),
                'maxOutputTokens' => $options['max_tokens'] ?? 8192,
                'responseMimeType' => $options['json'] ?? false ? 'application/json' : null,
            ], fn ($v) => $v !== null),
        ];

        $json = $this->http()->post("models/{$model}:generateContent", $body)->throw()->json();

        $parts = data_get($json, 'candidates.0.content.parts', []);
        $text = collect($parts)->reject(fn ($p) => $p['thought'] ?? false)->pluck('text')->filter()->implode('');

        if (trim($text) === '') {
            throw new RuntimeException('Gemini returned an empty response ('.data_get($json, 'candidates.0.finishReason', 'unknown').')');
        }

        return [
            'text' => trim($text),
            'tokens' => (int) data_get($json, 'usageMetadata.totalTokenCount', 0),
            'model' => (string) (data_get($json, 'modelVersion') ?: $model),
        ];
    }

    /**
     * @return list<list<float>>
     */
    public function embed(AnonymizedPayload $payload, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $this->assertIssued($payload, AnonymizedPayload::KIND_EMBEDDING);
        $texts = $payload->texts;
        $model = $this->embeddingModel();
        $dims = (int) config('lexlink.ai.embedding_dimensions', 768);

        $requests = array_map(fn (string $t) => array_filter([
            'model' => "models/{$model}",
            'content' => ['parts' => [['text' => $t]]],
            'taskType' => $taskType,
            'outputDimensionality' => $dims ?: null,
        ]), array_values($texts));

        $json = $this->http()->post("models/{$model}:batchEmbedContents", ['requests' => $requests])->throw()->json();

        return array_map(fn ($e) => self::normalize($e['values'] ?? []), $json['embeddings'] ?? []);
    }

    /** @return list<float> */
    public function embedQuery(AnonymizedPayload $payload): array
    {
        return $this->embed($payload, 'RETRIEVAL_QUERY')[0] ?? [];
    }

    /** @return list<array{name: string, displayName: ?string, methods: list<string>}> */
    public function listModels(): array
    {
        $models = [];
        $token = null;
        do {
            $json = $this->http()->get('models', array_filter(['pageSize' => 1000, 'pageToken' => $token]))->throw()->json();
            foreach ($json['models'] ?? [] as $m) {
                $models[] = [
                    'name' => str_replace('models/', '', $m['name']),
                    'displayName' => $m['displayName'] ?? null,
                    'methods' => $m['supportedGenerationMethods'] ?? [],
                ];
            }
            $token = $json['nextPageToken'] ?? null;
        } while ($token);

        return $models;
    }

    /** L2-normalise so cosine similarity == dot product (needed for reduced-dimension embeddings). */
    public static function normalize(array $v): array
    {
        $norm = sqrt(array_sum(array_map(fn ($x) => $x * $x, $v)));

        return $norm > 0 ? array_map(fn ($x) => $x / $norm, $v) : $v;
    }

    private function assertIssued(AnonymizedPayload $payload, string $kind): void
    {
        if ($payload->kind !== $kind || ! AnonymizationGateway::verify($payload)) {
            throw new RuntimeException('Refusing to call Gemini: payload was not issued by the AnonymizationGateway.');
        }
    }

    private function http(): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('GEMINI_API_KEY is not configured');
        }

        return Http::baseUrl(rtrim((string) config('lexlink.ai.gemini_base_url'), '/').'/')
            ->withHeaders(['x-goog-api-key' => (string) config('lexlink.ai.gemini_key')])
            ->acceptJson()
            ->timeout((int) config('lexlink.ai.timeout', 60))
            ->retry(2, 1500, fn ($e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 503], true)), throw: true);
    }
}

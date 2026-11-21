<?php

namespace App\Services\Ai\Anonymization;

use LogicException;

/**
 * The ONLY thing GeminiClient accepts. It cannot be built outside AnonymizationGateway:
 * the constructor is private (the gateway instantiates it through a scope-bound closure) and every
 * instance carries an HMAC seal over its content that GeminiClient re-verifies before any HTTP call.
 * A raw string therefore never reaches Gemini — passing one is a TypeError.
 */
final class AnonymizedPayload
{
    public const KIND_GENERATION = 'generation';

    public const KIND_EMBEDDING = 'embedding';

    /**
     * @param  list<string>  $texts  embedding inputs (empty for generation)
     * @param  array<string, mixed>  $report  AnonymizationReport::toArray()
     */
    private function __construct(
        public readonly string $kind,
        public readonly string $system,
        public readonly string $text,
        public readonly array $texts,
        public readonly array $report,
        public readonly string $seal,
    ) {}

    public function __clone()
    {
        throw new LogicException('AnonymizedPayload cannot be cloned.');
    }

    public function __serialize(): array
    {
        throw new LogicException('AnonymizedPayload cannot be serialized.');
    }

    public function __unserialize(array $data): void
    {
        throw new LogicException('AnonymizedPayload cannot be unserialized.');
    }

    /** Canonical content the seal is computed over. */
    public static function canonical(string $kind, string $system, string $text, array $texts): string
    {
        return $kind."\x1e".$system."\x1e".$text."\x1e".implode("\x1f", $texts);
    }

    public function content(): string
    {
        return self::canonical($this->kind, $this->system, $this->text, $this->texts);
    }
}

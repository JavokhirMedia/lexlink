<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Versioned prompts in resources/prompts/{name}.md (front matter `version: vN`, {{var}} placeholders).
 */
class PromptRepository
{
    /** @var array<string, array{version: string, body: string}> */
    private array $cache = [];

    /** @return array{version: string, body: string} */
    public function get(string $name): array
    {
        return $this->cache[$name] ??= $this->load($name);
    }

    public function system(): string
    {
        return $this->get('system')['body'];
    }

    /** @return array{0: string, 1: string} [rendered body, version "name@v1"] */
    public function render(string $name, array $vars): array
    {
        $p = $this->get($name);
        $body = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? ''), $p['body']);

        return [$body, $p['version']];
    }

    /** @return array{version: string, body: string} */
    private function load(string $name): array
    {
        $path = resource_path("prompts/{$name}.md");
        if (! File::exists($path)) {
            throw new RuntimeException("Prompt not found: {$name}");
        }
        $raw = File::get($path);
        $version = 'v1';
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $raw, $m)) {
            if (preg_match('/version:\s*(\S+)/', $m[1], $v)) {
                $version = $v[1];
            }
            $raw = substr($raw, strlen($m[0]));
        }

        return ['version' => $version, 'body' => trim($raw)];
    }
}

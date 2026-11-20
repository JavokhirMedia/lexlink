<?php

namespace App\Services\Ai\Lex;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Parses a lex.uz document page (https://lex.uz/docs/{id}) into articles.
 *
 * lex.uz renders each structural element as `<div class="{TYPE} lx_elem"><div name="{elementId}">text</div></div>`
 * inside `#divCont`. Article headings are CLAUSE_DEFAULT ("99-modda. Sarlavha"),
 * chapter headings TEXT_HEADER_DEFAULT, paragraphs ACT_TEXT. Inserted articles use
 * superscript numbers (7<sup>1</sup>-modda) — normalised to "7-1".
 */
class LexParser
{
    private const SUPERSCRIPT = ['0' => '⁰', '1' => '¹', '2' => '²', '3' => '³', '4' => '⁴', '5' => '⁵', '6' => '⁶', '7' => '⁷', '8' => '⁸', '9' => '⁹'];

    /**
     * @return array{title: string, adopted_at: ?string, number: ?string, articles: list<array{number: string, title: string, anchor: string, chapter: ?string, text: string}>}
     */
    public function parse(string $html): array
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        [$number, $adopted, $title] = $this->parseTitle($xpath);

        $articles = [];
        $current = null;
        $chapter = null;

        foreach ($xpath->query('//div[@id="divCont"]/div[contains(concat(" ", @class, " "), " lx_elem ")]') as $el) {
            /** @var DOMElement $el */
            $type = strtok($el->getAttribute('class'), ' ');
            $node = $xpath->query('.//div[@name]', $el)->item(0);
            if (! $node instanceof DOMElement) {
                continue;
            }
            $text = $this->text($node, $xpath);

            if ($type === 'TEXT_HEADER_DEFAULT') {
                $chapter = $text !== '' ? $text : $chapter;

                continue;
            }

            if ($type === 'CLAUSE_DEFAULT') {
                if ($current) {
                    $articles[] = $current;
                }
                $num = $this->articleNumber($node);
                $current = [
                    'number' => $num ?? (string) (count($articles) + 1),
                    'title' => trim(preg_replace('/^\s*[\d\s\-¹²³⁴⁵⁶⁷⁸⁹⁰]+-?modda\.?\s*/u', '', $text) ?? $text),
                    'heading' => $text,
                    'anchor' => $node->getAttribute('id'),
                    'chapter' => $chapter,
                    'paragraphs' => [],
                ];

                continue;
            }

            if ($type === 'ACT_TEXT' && $current && $text !== '') {
                $current['paragraphs'][] = $text;
            }
        }
        if ($current) {
            $articles[] = $current;
        }

        return [
            'title' => $title,
            'adopted_at' => $adopted,
            'number' => $number,
            'articles' => array_values(array_map(fn (array $a) => [
                'number' => $a['number'],
                'title' => $a['title'],
                'heading' => $a['heading'],
                'anchor' => $a['anchor'],
                'chapter' => $a['chapter'],
                'text' => implode("\n", $a['paragraphs']),
            ], $articles)),
        ];
    }

    /** "7<sup>1</sup>-modda" -> "7-1"; "99-modda" -> "99". */
    private function articleNumber(DOMElement $node): ?string
    {
        $html = $node->ownerDocument->saveHTML($node);
        if (preg_match('/>\s*(\d+)\s*(?:<sup>\s*(\d+)\s*<\/sup>)?\s*-\s*modda/u', $html, $m)) {
            return isset($m[2]) && $m[2] !== '' ? $m[1].'-'.$m[2] : $m[1];
        }

        return null;
    }

    /** Visible text of an element without lex.uz service nodes; superscripts kept as unicode. */
    private function text(DOMElement $node, DOMXPath $xpath): string
    {
        $clone = $node->cloneNode(true);
        foreach ($xpath->query('.//*[contains(@class,"lx_no_select") or contains(@class,"COMMENT") or contains(@class,"lx_elem2")]', $clone) as $junk) {
            $junk->parentNode?->removeChild($junk);
        }
        foreach (iterator_to_array($xpath->query('.//sup', $clone)) as $sup) {
            $sup->parentNode?->replaceChild($clone->ownerDocument->createTextNode(strtr(trim($sup->textContent), self::SUPERSCRIPT)), $sup);
        }

        $text = html_entity_decode($clone->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /** @return array{0: ?string, 1: ?string, 2: string} [number, adopted Y-m-d, title] */
    private function parseTitle(DOMXPath $xpath): array
    {
        $raw = (string) $xpath->evaluate('string(//title)');
        $raw = trim(str_replace("\u{00A0}", ' ', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $number = null;
        $adopted = null;
        if (preg_match('/^(.*?)\s*(\d{2})\.(\d{2})\.(\d{4})\.\s*(.+)$/u', $raw, $m)) {
            $number = trim(preg_replace('/-?сон$/u', '', trim($m[1])) ?? '') ?: null;
            $adopted = "{$m[4]}-{$m[3]}-{$m[2]}";
            $raw = trim($m[5]);
        }

        $act = trim((string) $xpath->evaluate('string(//div[contains(@class,"ACT_TITLE")]//div[@name])'));

        return [$number, $adopted, $act !== '' ? preg_replace('/\s+/u', ' ', $act) : $raw];
    }
}

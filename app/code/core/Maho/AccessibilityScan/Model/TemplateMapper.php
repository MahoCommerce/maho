<?php

/**
 * Maps axe-core violation snippets to their source .phtml templates by parsing
 * the template-hint wrappers embedded in the raw scanned HTML.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_TemplateMapper
{
    protected string $rawHtml;

    /**
     * Hint wrapper intervals as [start offset, end offset, nesting depth, template path]
     *
     * @var list<array{start: int, end: int, depth: int, template: string}>
     */
    protected array $intervals = [];

    public function __construct(string $rawHtml)
    {
        $this->rawHtml = $rawHtml;
        $this->parseHints();
    }

    /**
     * Resolve the innermost template containing the given HTML snippet
     *
     * @return array{0: ?string, 1: ?int} template file (relative to the Maho root) and best-effort line number
     */
    public function mapSnippet(?string $snippet): array
    {
        $snippet = trim((string) $snippet);
        if ($snippet === '' || $this->intervals === []) {
            return [null, null];
        }

        $offset = strpos($this->rawHtml, $snippet);
        if ($offset === false) {
            // Child hint wrappers may sit inside the element in the raw HTML;
            // fall back to the element's opening tag, which is never altered
            $tagEnd = strpos($snippet, '>');
            if ($tagEnd !== false) {
                $offset = strpos($this->rawHtml, substr($snippet, 0, $tagEnd + 1));
            }
        }
        if ($offset === false) {
            return [null, null];
        }

        $best = null;
        foreach ($this->intervals as $interval) {
            if ($offset >= $interval['start'] && $offset < $interval['end']
                && ($best === null || $interval['depth'] > $best['depth'])
            ) {
                $best = $interval;
            }
        }
        if ($best === null) {
            return [null, null];
        }

        return [$best['template'], $this->findLineInTemplate($best['template'], $snippet)];
    }

    /**
     * Tokenize all div open/close tags and record the offsets covered by each
     * template-hint wrapper (a position:relative dotted-border div whose first
     * child is a position:absolute label div carrying the file path in title)
     */
    protected function parseHints(): void
    {
        if ($this->rawHtml === '' || !preg_match_all('#<div\b[^>]*>|</div>#i', $this->rawHtml, $matches, PREG_OFFSET_CAPTURE)) {
            return;
        }

        /** @var list<array{offset: int, depth: int, is_wrapper: bool, template: ?string}> $stack */
        $stack = [];
        foreach ($matches[0] as [$tag, $offset]) {
            if ($tag[1] === '/') {
                $entry = array_pop($stack);
                if ($entry !== null && $entry['template'] !== null) {
                    $this->intervals[] = [
                        'start' => $entry['offset'],
                        'end' => $offset,
                        'depth' => $entry['depth'],
                        'template' => $entry['template'],
                    ];
                }
                continue;
            }

            $isWrapper = str_contains($tag, 'position:relative') && str_contains($tag, '1px dotted');
            if (!$isWrapper && $stack !== []
                && str_contains($tag, 'position:absolute') && str_contains($tag, 'z-index:998')
                && preg_match('/title="([^"]+\.phtml)"/', $tag, $m)
            ) {
                // Label div: attribute its template path to the enclosing wrapper
                $parent = count($stack) - 1;
                if ($stack[$parent]['is_wrapper'] && $stack[$parent]['template'] === null) {
                    $stack[$parent]['template'] = $m[1];
                }
            }

            $stack[] = [
                'offset' => $offset,
                'depth' => count($stack),
                'is_wrapper' => $isWrapper,
                'template' => null,
            ];
        }
    }

    /**
     * Best-effort line resolution: look for the snippet's class attribute or
     * opening tag inside the template source
     */
    protected function findLineInTemplate(string $template, string $snippet): ?int
    {
        $file = Mage::getBaseDir() . '/' . $template;
        if (str_contains($template, '..') || !is_file($file)) {
            return null;
        }

        $needles = [];
        if (preg_match('/class="([^"]+)"/', $snippet, $m)) {
            $needles[] = $m[1];
        }
        if (preg_match('/^<([a-z0-9-]+)/i', $snippet, $m)) {
            $needles[] = '<' . $m[1];
        }

        $lines = file($file);
        if ($lines === false) {
            return null;
        }

        foreach ($needles as $needle) {
            foreach ($lines as $i => $line) {
                if (str_contains($line, $needle)) {
                    return $i + 1;
                }
            }
        }
        return null;
    }
}

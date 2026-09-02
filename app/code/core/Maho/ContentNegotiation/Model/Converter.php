<?php

/**
 * Converts rendered HTML into markdown.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentNegotiation
 */

declare(strict_types=1);

use League\HTMLToMarkdown\HtmlConverter;

class Maho_ContentNegotiation_Model_Converter
{
    private ?HtmlConverter $converter = null;

    public function toMarkdown(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $markdown = $this->getConverter()->convert($html);

        // The library leaves text entities in place so its output stays HTML-safe. An agent reads
        // "&amp;" as noise, so decode them all except the angle brackets, which keep literal text
        // from turning into inline HTML.
        $markdown = (string) preg_replace_callback(
            '/&(?!lt;|gt;)(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);/i',
            static fn(array $match): string => html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $markdown,
        );

        return trim($markdown);
    }

    private function getConverter(): HtmlConverter
    {
        return $this->converter ??= new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'remove_nodes' => 'script style iframe noscript form button svg',
            'hard_break' => true,
            'use_autolinks' => true,
        ]);
    }
}

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

        return trim($this->getConverter()->convert($html));
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

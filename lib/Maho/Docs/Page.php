<?php

/**
 * One Markdown page of a docs source.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho
 */

declare(strict_types=1);

namespace Maho\Docs;

final readonly class Page
{
    /**
     * @param string $id the name of the source, a slash, and the path of the file in its folder
     * @param list<string> $adminPages the admin pages that the page explains, as "controller/action" or "controller" for every action
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public Source $source,
        public string $file,
        public array $adminPages = [],
    ) {}

    /** Whether the page explains the admin page of $route, given as "controller/action". */
    public function explains(string $route): bool
    {
        $controller = strtok($route, '/');
        return array_any($this->adminPages, fn($adminPage) => $adminPage === $route || $adminPage === $controller);
    }

    /** The text of the page without its front matter and its version badges, cut to $maxChars. */
    public function body(int $maxChars = Library::MAX_PAGE_CHARS): string
    {
        $body = (string) preg_replace(Library::VERSION_BADGE_PATTERN, '', Library::stripFrontMatter((string) file_get_contents($this->file)));

        return mb_strlen($body) > $maxChars ? mb_substr($body, 0, $maxChars) . "\n\n[The page is cut here.]" : $body;
    }
}

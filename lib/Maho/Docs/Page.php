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
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public Source $source,
        public string $file,
    ) {}

    /** The text of the page without its front matter and its version badges, cut to $maxChars. */
    public function body(int $maxChars = Library::MAX_PAGE_CHARS): string
    {
        $body = (string) preg_replace(Library::VERSION_BADGE_PATTERN, '', Library::stripFrontMatter((string) file_get_contents($this->file)));

        return mb_strlen($body) > $maxChars ? mb_substr($body, 0, $maxChars) . "\n\n[The page is cut here.]" : $body;
    }
}

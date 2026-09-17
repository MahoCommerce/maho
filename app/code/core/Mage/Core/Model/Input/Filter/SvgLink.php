<?php

/**
 * Limits a link to a scheme that opens a page, a mail client or a dialer.
 *
 * The purifier gets this rule twice, from Symfony and from here, which costs nothing. An uploaded
 * .svg file gets it only from here, and media/ serves such a file from the origin of the store,
 * so a `javascript:` link in it would run code against that origin.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;
use Uri\WhatWg\Url;

class Mage_Core_Model_Input_Filter_SvgLink implements AttributeSanitizerInterface
{
    /** Mage_Core_Helper_Purifier passes this list to allowLinkSchemes(), so both paths read one list. */
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public const ELEMENTS = ['a'];

    public const ATTRIBUTES = ['href'];

    /** @deprecated since 26.10, unused: the filter reads the scheme with Uri\WhatWg\Url */
    public const SCHEME_PATTERN = '/^([A-Za-z][A-Za-z0-9+.-]*):/';

    /**
     * A value with no scheme is relative, and the store resolves it against itself. The store
     * answers over http or https, and this list allows both, so a base on either one gives the
     * same verdict.
     */
    private const BASE = 'https://store.invalid/';

    private static ?Url $base = null;

    #[\Override]
    public function getSupportedElements(): ?array
    {
        return self::ELEMENTS;
    }

    #[\Override]
    public function getSupportedAttributes(): ?array
    {
        return self::ATTRIBUTES;
    }

    #[\Override]
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $this->isSafeValue($value) ? $value : null;
    }

    /**
     * Reads the scheme with the parser a browser uses, so the two never disagree.
     *
     * The WHATWG parser strips the control, tab and newline characters that a browser strips, and
     * it lower cases the scheme. A value it cannot parse opens nothing in a browser either, so
     * this method drops it. Do not read the scheme with parse_url(): it fails on values that a
     * browser reads, and it calls `javascript:///%0Aalert(1)` relative.
     */
    private function isSafeValue(string $value): bool
    {
        $url = Url::parse($value, self::$base ??= new Url(self::BASE));

        return $url !== null && in_array($url->getScheme(), self::SCHEMES, true);
    }
}

<?php

/**
 * Limits a link to a scheme that opens a page, a mail client or a dialer.
 *
 * The purifier gets this rule twice, from Symfony and from here, and that costs nothing. An
 * uploaded .svg file gets it only from here. media/ serves such a file from the origin of the
 * store, so a `javascript:` link in it runs code against that origin.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class Mage_Core_Model_Input_Filter_SvgLink implements AttributeSanitizerInterface
{
    /** Mage_Core_Helper_Purifier passes this list to allowLinkSchemes(), so one list governs both. */
    public const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public const ELEMENTS = ['a'];

    public const ATTRIBUTES = ['href'];

    /** The scheme grammar of RFC 3986: a letter, then letters, digits, `+`, `-` and `.`. */
    public const SCHEME_PATTERN = '/^([A-Za-z][A-Za-z0-9+.-]*):/';

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
     * A value with no scheme is relative, and the store resolves it against itself.
     *
     * A browser removes a control character from a URL before it reads the scheme, so this method
     * does the same. The parser resolves a character reference earlier still, so a scheme cannot
     * hide behind one.
     *
     * Do not read the scheme with parse_url(). It fails on values that a browser reads, and it
     * reports that failure as it reports a relative value. It calls `javascript:///%0Aalert(1)`
     * safe.
     */
    private function isSafeValue(string $value): bool
    {
        $value = (string) preg_replace('/[\x00-\x20]/', '', $value);

        if (preg_match(self::SCHEME_PATTERN, $value, $match) !== 1) {
            return true;
        }

        return in_array(strtolower($match[1]), self::SCHEMES, true);
    }
}

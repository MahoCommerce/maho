<?php

/**
 * Limits an SVG paint attribute to a color or to a reference inside the same document.
 *
 * The allowlist tests names. It does not test values. A paint attribute can hold a URL. The value
 * `fill="url(https://evil.example/x)"` uses an allowed attribute on an allowed element. The
 * storefront then reads a document from another server, and that document changes how the page
 * looks.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Security\SvgAllowlist;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class Mage_Core_Model_Input_Filter_SvgPaint implements AttributeSanitizerInterface
{
    public const LOCAL_REFERENCE_PATTERN = '/^url\(\s*[\'"]?#[A-Za-z0-9_.:-]+[\'"]?\s*\)$/';

    public const KEYWORDS = ['none', 'currentcolor', 'transparent', 'inherit', 'context-fill', 'context-stroke'];

    public const HEX_COLOR_PATTERN = '/^#[0-9A-Fa-f]{3,8}$/';

    public const FUNCTIONAL_COLOR_PATTERN = '/^(?:rgba?|hsla?|hwb|lab|lch|oklab|oklch|color)\([0-9A-Za-z%.,\/\s+-]*\)$/';

    public const NAMED_COLOR_PATTERN = '/^[A-Za-z]{3,20}$/';

    #[\Override]
    public function getSupportedElements(): ?array
    {
        return array_map(strtolower(...), SvgAllowlist::elementNames(true));
    }

    #[\Override]
    public function getSupportedAttributes(): ?array
    {
        return SvgAllowlist::PAINT_ATTRIBUTES;
    }

    #[\Override]
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $this->isSafeValue($value) ? $value : null;
    }

    /** Public, because the file validator uses the same rule for an uploaded .svg file. */
    public function isSafeValue(string $value): bool
    {
        $value = trim($value);

        return preg_match(self::LOCAL_REFERENCE_PATTERN, $value) === 1
            || in_array(strtolower($value), self::KEYWORDS, true)
            || preg_match(self::HEX_COLOR_PATTERN, $value) === 1
            || preg_match(self::FUNCTIONAL_COLOR_PATTERN, $value) === 1
            || preg_match(self::NAMED_COLOR_PATTERN, $value) === 1;
    }
}

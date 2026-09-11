<?php

/**
 * Limits an animation to the attributes that the author may already write.
 *
 * Every HTML filter reads the document once and then trusts it. An animation breaks that rule,
 * because it changes an attribute after the page loads. The markup
 * `<animate attributeName="href" values="javascript:alert(1)">` contains no bad element and no bad
 * text, but it makes a link run code one second later.
 *
 * Two rules stop this. One rule alone is not enough.
 *
 * 1. `attributeName` must name an attribute that the allowlist permits. This rule removes `href`
 *    and `onload`.
 * 2. A value attribute must contain no URL scheme and no reference to another document. Rule 1
 *    still permits `attributeName="fill"`, and a value can then point to another server.
 *
 * An animation without `attributeName` changes nothing, so a removed attribute makes the element
 * safe.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Security\SvgAllowlist;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class Mage_Core_Model_Input_Filter_SvgAnimation implements AttributeSanitizerInterface
{
    public const VALUE_ATTRIBUTES = ['from', 'to', 'by', 'values', 'begin', 'end'];

    /** The colon matches every URL scheme. An animated value never needs one. */
    public const UNSAFE_VALUE_PATTERN = '/[:\\\\]|url\(\s*[\'"]?(?!#)/i';

    public const ATTRIBUTE_TYPES = ['xml', 'css', 'auto'];

    #[\Override]
    public function getSupportedElements(): ?array
    {
        return array_map(strtolower(...), SvgAllowlist::ANIMATION_ELEMENTS);
    }

    #[\Override]
    public function getSupportedAttributes(): ?array
    {
        return array_map(strtolower(...), [
            'attributeName',
            'attributeType',
            ...self::VALUE_ATTRIBUTES,
        ]);
    }

    #[\Override]
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $this->isSafeValue($attribute, $value) ? $value : null;
    }

    /** Public, because the file validator uses the same rule for an uploaded .svg file. */
    public function isSafeValue(string $attribute, string $value): bool
    {
        $value = trim($value);

        return match (strtolower($attribute)) {
            'attributename' => $this->isAnimatable($value),
            'attributetype' => in_array(strtolower($value), self::ATTRIBUTE_TYPES, true),
            default => preg_match(self::UNSAFE_VALUE_PATTERN, $value) !== 1,
        };
    }

    private function isAnimatable(string $name): bool
    {
        return array_any(SvgAllowlist::animatableAttributes(), fn($animatable) => strcasecmp($animatable, $name) === 0);
    }
}

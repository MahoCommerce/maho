<?php

/**
 * Limits an animation to the attributes that the author may already write.
 *
 * A sanitizer reads the document once. An animation changes an attribute after the page loads,
 * so both rules below are needed: the name rule alone still allows `attributeName="fill"`, and
 * that value can then name another server.
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

    /**
     * The colon matches every URL scheme, and the backslash matches a CSS escape. An animated
     * value needs neither. The quantifiers are possessive, so the quote in `url('#a')` cannot be
     * given up on a retry.
     */
    public const UNSAFE_VALUE_PATTERN = '/[:\\\\]|url\(\s*+[\'"]?+(?!#)/i';

    public const ATTRIBUTE_TYPES = ['xml', 'css', 'auto'];

    /** On an animation element `fill` says whether the end state holds. It paints nothing. */
    public const FILL_MODES = ['freeze', 'remove'];

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
            'fill',
            ...self::VALUE_ATTRIBUTES,
        ]);
    }

    #[\Override]
    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $this->isSafeValue($attribute, $value) ? $value : null;
    }

    private function isSafeValue(string $attribute, string $value): bool
    {
        $value = trim($value);

        return match (strtolower($attribute)) {
            'attributename' => $this->isAnimatable($value),
            'attributetype' => in_array(strtolower($value), self::ATTRIBUTE_TYPES, true),
            'fill' => in_array(strtolower($value), self::FILL_MODES, true),
            default => preg_match(self::UNSAFE_VALUE_PATTERN, $value) !== 1,
        };
    }

    private function isAnimatable(string $name): bool
    {
        return SvgAllowlist::containsName(SvgAllowlist::animatableAttributes(), $name);
    }
}

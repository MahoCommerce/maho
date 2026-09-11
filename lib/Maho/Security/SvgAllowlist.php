<?php

/**
 * The part of SVG that an author may put in content.
 *
 * Mage_Core_Helper_Purifier and Mage_Core_Model_File_Validator_Svg share this list. One list keeps
 * the two paths equal. This file gives each name its standard spelling. Each user of the list
 * changes the letter case: the purifier needs lower case, the file validator needs the standard
 * spelling, because a browser reads a .svg file as XML.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

final class SvgAllowlist
{
    /** Allowed on every element below. `class` and `style` come from Purifier::EXTRA_ATTRIBUTES. */
    public const COMMON_ATTRIBUTES = [
        'transform', 'opacity', 'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'clip-path',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'id',
    ];

    /**
     * The attributes of each element, in addition to COMMON_ATTRIBUTES.
     *
     * You cannot add `<title>` here. In body context Symfony keeps only the elements that its
     * internal HEAD_ELEMENTS list contains, and `title` is in that list. Symfony therefore ignores
     * allowElement('title') and reports no error. Use aria-label to name an icon.
     *
     * An element that holds text can change how a browser reads the markup that follows it. If you
     * add a name to this list, run the fixed-point test again.
     *
     * @var array<string, list<string>>
     */
    public const ELEMENTS = [
        'svg'            => ['viewBox', 'xmlns', 'version', 'width', 'height', 'x', 'y', 'preserveAspectRatio', 'role'],
        'g'              => [],
        'defs'           => [],
        'symbol'         => ['viewBox', 'preserveAspectRatio'],
        'path'           => ['d', 'pathLength'],
        'circle'         => ['cx', 'cy', 'r'],
        'ellipse'        => ['cx', 'cy', 'rx', 'ry'],
        'rect'           => ['x', 'y', 'width', 'height', 'rx', 'ry'],
        'line'           => ['x1', 'y1', 'x2', 'y2'],
        'polyline'       => ['points'],
        'polygon'        => ['points'],
        // No href. A gradient copies the stops of another gradient through a full URL, and that
        // URL can point to a different server.
        'linearGradient' => ['x1', 'y1', 'x2', 'y2', 'gradientUnits', 'gradientTransform', 'spreadMethod'],
        'radialGradient' => ['cx', 'cy', 'r', 'fx', 'fy', 'fr', 'gradientUnits', 'gradientTransform', 'spreadMethod'],
        'stop'           => ['offset', 'stop-color', 'stop-opacity'],
        'clipPath'       => ['clipPathUnits'],
        'text'           => ['x', 'y', 'dx', 'dy', 'font-size', 'font-family', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline', 'letter-spacing', 'word-spacing', 'writing-mode'],
        'tspan'          => ['x', 'y', 'dx', 'dy', 'font-size', 'font-family', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline'],
        'desc'           => [],
        'animate'        => self::ANIMATION_ATTRIBUTES,
        'animateTransform' => [...self::ANIMATION_ATTRIBUTES, 'type'],
        'set'            => self::ANIMATION_ATTRIBUTES,
    ];

    /**
     * Allowed only in an uploaded .svg file. A browser reads such a file as XML, so these elements
     * never reach an HTML parser or Symfony.
     *
     * @var array<string, list<string>>
     */
    public const FILE_ONLY_ELEMENTS = [
        'title'    => [],
        'metadata' => [],
    ];

    /**
     * Names that ELEMENTS must never contain. Tests/Backend/Unit/Core/Model/File/Validator/SvgTest
     * reads this list, so a name in both lists makes the build fail.
     *
     * script, style and handler run code. foreignObject puts HTML inside SVG. use, image and mpath
     * read a different document. animateMotion needs mpath to find its path.
     */
    public const NEVER_ALLOWED = [
        'script', 'style', 'foreignObject', 'use', 'image', 'mpath', 'animateMotion', 'handler',
    ];

    /** Safe only with the value rules of Mage_Core_Model_Input_Filter_SvgAnimation. */
    public const ANIMATION_ATTRIBUTES = [
        'attributeName', 'attributeType', 'from', 'to', 'by', 'values',
        'dur', 'begin', 'end', 'repeatCount', 'repeatDur', 'restart',
        'calcMode', 'keyTimes', 'keySplines', 'additive', 'accumulate',
    ];

    public const ANIMATION_ELEMENTS = ['animate', 'animateTransform', 'set'];

    /** An animation element uses `fill` for a different purpose, so the two value filters differ. */
    public const PAINT_ATTRIBUTES = ['fill', 'stroke', 'clip-path'];

    /** @return list<string> standard spelling */
    public static function elementNames(bool $includeFileOnly = false): array
    {
        return $includeFileOnly
            ? [...array_keys(self::ELEMENTS), ...array_keys(self::FILE_ONLY_ELEMENTS)]
            : array_keys(self::ELEMENTS);
    }

    /**
     * Returns null if the list does not contain $element. Letter case does not matter.
     *
     * @return list<string>|null
     */
    public static function attributesFor(string $element, bool $includeFileOnly = false): ?array
    {
        $elements = $includeFileOnly
            ? [...self::ELEMENTS, ...self::FILE_ONLY_ELEMENTS]
            : self::ELEMENTS;

        foreach ($elements as $name => $attributes) {
            if (strcasecmp($name, $element) === 0) {
                // No COMMON_ATTRIBUTES here. An animation element uses `fill` for a different
                // purpose, and geometry on it has no meaning.
                if (in_array($name, self::ANIMATION_ELEMENTS, true)) {
                    return array_values(array_unique($attributes));
                }
                return array_values(array_unique([...$attributes, ...self::COMMON_ATTRIBUTES]));
            }
        }

        return null;
    }

    public static function canonicalElement(string $element, bool $includeFileOnly = false): ?string
    {
        foreach (self::elementNames($includeFileOnly) as $name) {
            if (strcasecmp($name, $element) === 0) {
                return $name;
            }
        }

        return null;
    }

    public static function allowsAttribute(string $element, string $attribute, bool $includeFileOnly = false): bool
    {
        return array_any(self::attributesFor($element, $includeFileOnly) ?? [], fn($allowed) => strcasecmp($allowed, $attribute) === 0);
    }

    /**
     * An animation may change only an attribute that ELEMENTS already allows. This rule removes
     * `attributeName="href"` and `attributeName="onload"`. The rule stays correct when ELEMENTS
     * grows, because it needs no list of bad names.
     *
     * The three names below are removed as well. Each one points to another object. They do not
     * change how an element looks.
     *
     * @return list<string>
     */
    public static function animatableAttributes(): array
    {
        $animatable = [];
        foreach (self::ELEMENTS as $element => $attributes) {
            if (in_array($element, self::ANIMATION_ELEMENTS, true)) {
                continue;
            }
            foreach ([...$attributes, ...self::COMMON_ATTRIBUTES] as $attribute) {
                $animatable[$attribute] = true;
            }
        }

        unset($animatable['id'], $animatable['xmlns'], $animatable['version']);

        return array_keys($animatable);
    }

    /**
     * Lower case names for the editor. TipTap then keeps the same markup that a save keeps. The
     * value rules stay on the server. The sanitize-preview endpoint reports what they remove.
     *
     * @return array<string, list<string>>
     */
    public static function forEditor(): array
    {
        $list = [];
        foreach (array_keys(self::ELEMENTS) as $element) {
            $list[strtolower($element)] = array_map(
                strtolower(...),
                self::attributesFor($element) ?? [],
            );
        }

        return $list;
    }
}

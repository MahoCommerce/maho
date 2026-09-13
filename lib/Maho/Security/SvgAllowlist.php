<?php

/**
 * The part of SVG that an author may put in content.
 *
 * The purifier, the file validator and the editor all read this list, so the three agree.
 *
 * Every name here has its standard spelling. The purifier lowers the case, because Symfony
 * compares lower case names. A browser restores the spelling when it reads the page. A .svg file
 * and the editor DOM read a name letter by letter, so both keep this spelling.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

final class SvgAllowlist
{
    /**
     * Mage_Core_Helper_Purifier reads this list for its own EXTRA_ATTRIBUTES, so it also reaches
     * every element outside a graphic. A `data-*` name cannot be listed, so each path tests that
     * prefix instead.
     */
    public const GLOBAL_ATTRIBUTES = ['class', 'style', 'aria-label', 'aria-current', 'aria-hidden'];

    /** Allowed on every element below. */
    public const COMMON_ATTRIBUTES = [
        'transform', 'opacity', 'fill', 'fill-opacity', 'fill-rule', 'clip-rule', 'clip-path',
        'stroke', 'stroke-width', 'stroke-opacity', 'stroke-linecap', 'stroke-linejoin',
        'stroke-miterlimit', 'stroke-dasharray', 'stroke-dashoffset', 'id',
    ];

    /**
     * The attributes of each element, in addition to COMMON_ATTRIBUTES.
     *
     * You cannot add `<title>` here. Symfony reserves that name for the document head, so it
     * ignores allowElement('title') and reports no error. Use aria-label to name an icon.
     *
     * An element that holds text can change how a browser reads the markup after it. Run the
     * fixed-point test again when you add a name here.
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
        // A browser reads the content of <desc> as HTML, not as SVG. The editor cannot.
        'desc'     => [],
    ];

    /**
     * Elements that the W3C baseline already allows inside a graphic.
     *
     * buildConfig() must not name them. allowElement() replaces the attributes that the baseline
     * gives a name, so naming `a` would narrow every link on the page. The file validator and the
     * editor build their list from nothing, so both read this one.
     *
     * @var array<string, list<string>>
     */
    public const BASELINE_ELEMENTS = [
        'a' => [
            'href', 'target', 'rel', 'title', 'download', 'hreflang', 'type',
            // The baseline grants a link 182 attributes. These are the ones an author writes, and
            // a path that builds its own list drops what it does not name, which makes the editor
            // report a loss that the save does not cause.
            'id', 'role', 'lang', 'dir', 'tabindex', 'referrerpolicy', 'name', 'translate',
        ],
    ];

    /**
     * Names that ELEMENTS must never contain. tests/Backend/Unit/Core/Model/File/Validator/SvgTest.php
     * reads this list, so a name in both lists fails a test.
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
        'calcMode', 'keyTimes', 'keySplines', 'additive', 'accumulate', 'fill',
    ];

    public const ANIMATION_ELEMENTS = ['animate', 'animateTransform', 'set'];

    /** An animation element uses `fill` for a different purpose, so the two value filters differ. */
    public const PAINT_ATTRIBUTES = ['fill', 'stroke', 'clip-path'];

    /**
     * Every element, keyed by lower case name, with the standard spelling of every name kept.
     * One index answers each method below, so none of them scans the three lists again.
     *
     * @var array<string, array{name: string, attributes: array<string, string>}>|null
     */
    private static ?array $index = null;

    /** @var list<string>|null */
    private static ?array $animatable = null;

    /** @return array<string, array{name: string, attributes: array<string, string>}> */
    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }

        self::$index = [];
        // The baseline owns the attributes of an HTML element. COMMON_ATTRIBUTES here would let
        // the editor keep a paint attribute that a save removes.
        foreach ([[self::ELEMENTS, true], [self::FILE_ONLY_ELEMENTS, true], [self::BASELINE_ELEMENTS, false]] as [$group, $isSvg]) {
            foreach ($group as $name => $attributes) {
                // No COMMON_ATTRIBUTES on an animation element. Geometry has no meaning there.
                $common = $isSvg && !in_array($name, self::ANIMATION_ELEMENTS, true) ? self::COMMON_ATTRIBUTES : [];
                $map = [];
                foreach ([...$attributes, ...$common, ...self::GLOBAL_ATTRIBUTES] as $attribute) {
                    $map[strtolower($attribute)] ??= $attribute;
                }

                self::$index[strtolower($name)] = ['name' => $name, 'attributes' => $map];
            }
        }

        return self::$index;
    }

    /**
     * Every name the policy allows. A consumer that must narrow it reads the three lists itself.
     *
     * @return list<string> standard spelling
     */
    public static function elementNames(): array
    {
        return array_values(array_column(self::index(), 'name'));
    }

    /**
     * Returns null when the policy does not allow $element. Letter case does not matter.
     *
     * @return list<string>|null standard spelling
     */
    public static function attributesFor(string $element): ?array
    {
        $entry = self::index()[strtolower($element)] ?? null;

        return $entry === null ? null : array_values($entry['attributes']);
    }

    public static function canonicalElement(string $element): ?string
    {
        return self::index()[strtolower($element)]['name'] ?? null;
    }

    public static function allowsAttribute(string $element, string $attribute): bool
    {
        return isset(self::index()[strtolower($element)]['attributes'][strtolower($attribute)]);
    }

    /**
     * Two names that differ only in letter case are one name here, so every comparison uses this.
     *
     * @param list<string> $names
     */
    public static function containsName(array $names, string $name): bool
    {
        return array_any($names, fn(string $candidate): bool => strcasecmp($candidate, $name) === 0);
    }

    /**
     * An animation may change only an attribute that ELEMENTS already allows. That rule removes
     * `attributeName="href"` and `attributeName="onload"` without a list of bad names.
     *
     * It drops `id`, `xmlns` and `version` as well. None of them changes how a graphic looks, and
     * an animated `id` would move the target of every local reference.
     *
     * @return list<string>
     */
    public static function animatableAttributes(): array
    {
        if (self::$animatable !== null) {
            return self::$animatable;
        }

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

        return self::$animatable = array_keys($animatable);
    }

    /**
     * The allowlist for the editor, keyed by lower case name.
     *
     * TipTap then keeps the same markup that a save keeps. The value rules stay on the server. The
     * sanitize-preview endpoint reports what they remove.
     *
     * Each entry also carries the standard spelling. The editor writes lower case markup, which
     * matches what the purifier stores, but it builds the live graphic with the DOM. The SVG DOM
     * reads a name letter by letter, so `lineargradient` and `viewbox` draw nothing there.
     *
     * @return array<string, array{name: string, attributes: array<string, string>}>
     */
    public static function forEditor(): array
    {
        // Not FILE_ONLY_ELEMENTS: a save cannot keep <title> inline, so the editor must not claim
        // it and then report a loss the save does not cause.
        return array_diff_key(self::index(), array_change_key_case(self::FILE_ONLY_ELEMENTS));
    }
}

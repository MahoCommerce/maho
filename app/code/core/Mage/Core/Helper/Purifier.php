<?php

/**
 * HTML sanitization for admin-authored and AI-generated rich content.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Security\SvgAllowlist;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

class Mage_Core_Helper_Purifier extends Mage_Core_Helper_Abstract
{
    /**
     * Attributes allowed on every element, on top of the W3C baseline.
     *
     * SvgAllowlist owns the list, so one statement governs every path. `class` and `style` sit
     * outside allowSafeElements(), because they enable CSS injection. The WYSIWYG emits both: it
     * preserves them on every node and stores alignment as inline style. Dropping them would
     * restyle every existing page. The CSS-level vectors
     * (`expression()`, `behavior:`, `javascript:`) are left to the regex pass in
     * Mage_Core_Model_Input_Filter_MaliciousCode, which runs before this.
     *
     * The baseline has no `aria-*` attribute at all, and the theme's own components read them:
     * DaisyUI's rating fills its stars up to the one marked `aria-current`, so authored star
     * markup lost its fill on save without it.
     */
    public const EXTRA_ATTRIBUTES = SvgAllowlist::GLOBAL_ATTRIBUTES;

    /**
     * Input length ceiling, matching the storage the sanitized value is headed for.
     *
     * The sanitizer defaults to 20000 characters and silently truncates past it, which would cut
     * the tail off an ordinary CMS page on save. Symfony warns that disabling the cap entirely
     * invites a DoS, so rather than -1 this matches the declared length of the `content` column in
     * Mage/Cms/sql/schema.php: 2 MiB, which the DBAL maps to MEDIUMTEXT. Input past the cap is
     * still truncated silently, but nothing within the column's declared size can be.
     */
    public const MAX_INPUT_LENGTH = 2_097_152;

    /**
     * Matches a data-* attribute name in the raw input.
     *
     * The sanitizer matches allowed attributes by exact name and has no wildcard, so a `data-*`
     * allowance cannot be expressed in config. The names are read off the content instead: they are
     * inert by definition, carry no browser behaviour, and merchants put arbitrary ones on CMS
     * markup for sliders and other JS, so an enumerated list would always be incomplete.
     */
    public const DATA_ATTRIBUTE_PREFIX = 'data-';

    public const DATA_ATTRIBUTE_PATTERN = '/\b' . self::DATA_ATTRIBUTE_PREFIX . '[a-z][a-z0-9_-]*/i';

    /**
     * How many per-data-attribute-set sanitizers to keep.
     *
     * The cache key comes from the content being sanitized, so a loop over entities with varied
     * markup would otherwise add an entry per distinct set and hold every one for the rest of the
     * request. The cap keeps the common case (one shape of content, reused) free while bounding
     * a mass save; building a sanitizer is cheap enough that a miss only costs the config.
     */
    public const SANITIZER_CACHE_SIZE = 32;

    /**
     * HTML names that the W3C baseline allows and that SVG uses for a different purpose.
     *
     * The sanitizer compares element names and ignores the namespace. The baseline therefore
     * allows these names inside `<svg>` as well. A browser changes a plain `<image>` into `<img>`,
     * but inside `<svg>` the element stays an SVG `<image>` and reads a document from a URL.
     *
     * `font` is a second such name, and this list leaves it out. The baseline holds no child of
     * an SVG `<font>`, so the element draws nothing and fetches nothing. A rule would only damage
     * a legacy HTML `<font>`. `a` is a third, and an author needs a link.
     */
    public const SVG_ELEMENT_NAME_COLLISIONS = ['image'];

    /** @var array<string, HtmlSanitizerInterface> */
    protected array $sanitizerCache = [];

    public function __construct(protected ?HtmlSanitizerInterface $sanitizer = null) {}

    /**
     * The sanitization policy.
     *
     * Built on the W3C Sanitizer API baseline, which is HTML5-native, so `video`, `figure`,
     * `details` and `section` survive a save. Anything absent from that baseline falls to the
     * default Drop action, which covers `script`, `iframe`, `object` and `embed`, and form controls
     * along with them. Forms are left dropped as the W3C baseline has them: the supported way to
     * put one on a page is a block or widget, not markup pasted into a content field.
     *
     * The baseline contains no SVG element. \Maho\Security\SvgAllowlist adds them, in lower case,
     * because Symfony compares and writes lower case names. The stored markup then holds `viewbox`
     * and `lineargradient`. This is correct. The HTML5 rules for SVG give the browser the standard
     * spelling again when it reads the page. A mixed-case name here matches nothing.
     */
    public static function buildConfig(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            // Relative URLs default to being dropped, which would strip the href from every
            // internal link and the src from every locally hosted image.
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            // Media defaults to allowing data: URIs; keep to real transports so a base64 payload
            // cannot ride in on an img src.
            ->allowMediaSchemes(['http', 'https'])
            // Symfony allows these by default. Naming them keeps one statement of the rule.
            ->allowLinkSchemes(Mage_Core_Model_Input_Filter_SvgLink::SCHEMES)
            ->withMaxInputLength(self::MAX_INPUT_LENGTH);

        // Not elementNames(). allowElement() replaces what the baseline grants a name, and
        // elementNames() also holds the two names that only a file may use.
        // SvgAllowlist::BASELINE_ELEMENTS and FILE_ONLY_ELEMENTS say why.
        foreach (array_keys(SvgAllowlist::ELEMENTS) as $element) {
            $config = $config->allowElement(
                strtolower($element),
                array_map(strtolower(...), SvgAllowlist::attributesFor($element) ?? []),
            );
        }

        foreach (self::SVG_ELEMENT_NAME_COLLISIONS as $element) {
            $config = $config->dropElement($element);
        }

        // Keep this loop after every allowElement() call above. The '*' applies only to the
        // elements that are allowed at this moment. In the other order, an SVG loses its class
        // and its aria-label.
        foreach (self::EXTRA_ATTRIBUTES as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        foreach (self::attributeSanitizers() as $sanitizer) {
            $config = $config->withAttributeSanitizer($sanitizer);
        }

        return $config;
    }

    /**
     * The value filters. The allowlist cannot express them, because it tests names only.
     *
     * buildConfig() registers them, and Maho\Security\SvgDocumentSanitizer reads them back off
     * the config, so a new filter reaches both paths at once.
     *
     * @return list<AttributeSanitizerInterface>
     */
    public static function attributeSanitizers(): array
    {
        return [
            new Mage_Core_Model_Input_Filter_SvgPaint(),
            new Mage_Core_Model_Input_Filter_SvgAnimation(),
            new Mage_Core_Model_Input_Filter_SvgLink(),
        ];
    }

    /**
     * Purify HTML content.
     *
     * @param array|string $content
     * @return array|string
     */
    public function purify($content)
    {
        if (is_array($content)) {
            return array_map($this->purify(...), $content);
        }

        $content = (string) $content;

        return $this->getSanitizer($content)->sanitize($content);
    }

    /**
     * The sanitizer to use for this content, allowing the data-* attributes it happens to carry.
     *
     * An injected sanitizer is always used as-is, so a test or a module can substitute its own.
     */
    protected function getSanitizer(string $content): HtmlSanitizerInterface
    {
        if ($this->sanitizer !== null) {
            return $this->sanitizer;
        }

        preg_match_all(self::DATA_ATTRIBUTE_PATTERN, $content, $matches);
        $dataAttributes = array_unique(array_map(strtolower(...), $matches[0]));
        sort($dataAttributes);

        $key = implode(',', $dataAttributes);
        if (!isset($this->sanitizerCache[$key])) {
            $config = self::buildConfig();
            foreach ($dataAttributes as $attribute) {
                $config = $config->allowAttribute($attribute, '*');
            }
            if (count($this->sanitizerCache) >= self::SANITIZER_CACHE_SIZE) {
                array_shift($this->sanitizerCache);
            }
            $this->sanitizerCache[$key] = new HtmlSanitizer($config);
        }

        return $this->sanitizerCache[$key];
    }
}

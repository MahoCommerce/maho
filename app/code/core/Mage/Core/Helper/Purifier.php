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

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class Mage_Core_Helper_Purifier extends Mage_Core_Helper_Abstract
{
    /**
     * Attributes allowed on every element, on top of the W3C baseline.
     *
     * Both are excluded from `allowSafeElements()` because they enable CSS injection, and both are
     * emitted by the WYSIWYG: the TipTap setup preserves `class` and `style` on every node, and
     * stores text alignment and vertical alignment as inline style. Dropping them would restyle
     * every existing page and break the editor's own output, so the CSS-level vectors
     * (`expression()`, `behavior:`, `javascript:`) are left to the regex pass in
     * Mage_Core_Model_Input_Filter_MaliciousCode, which runs before this.
     */
    public const EXTRA_ATTRIBUTES = ['class', 'style'];

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
    public const DATA_ATTRIBUTE_PATTERN = '/\bdata-[a-z][a-z0-9_-]*/i';

    /**
     * How many per-data-attribute-set sanitizers to keep.
     *
     * The cache key comes from the content being sanitized, so a loop over entities with varied
     * markup would otherwise add an entry per distinct set and hold every one for the rest of the
     * request. The cap keeps the common case (one shape of content, reused) free while bounding
     * a mass save; building a sanitizer is cheap enough that a miss only costs the config.
     */
    public const SANITIZER_CACHE_SIZE = 32;

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
            ->withMaxInputLength(self::MAX_INPUT_LENGTH);

        foreach (self::EXTRA_ATTRIBUTES as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        return $config;
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

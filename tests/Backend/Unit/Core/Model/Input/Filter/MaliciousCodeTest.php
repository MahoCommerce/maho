<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Core_Model_Input_Filter_MaliciousCode::linkFilter', function () {
    beforeEach(function () {
        $this->filter = new Mage_Core_Model_Input_Filter_MaliciousCode();
    });

    it('preserves UTF-8 multi-byte characters when content contains a link', function () {
        // Regression: loadHTML() without an encoding hint defaulted to ISO-8859-1
        // and mangled UTF-8 (e.g. "ö" -> "&Atilde;&para;"), corrupting stored
        // content via _beforeSave(). The bug only triggered when an <a> tag was
        // present, since the no-link early return skips the DOMDocument round-trip.
        $result = $this->filter->linkFilter('<div>Grüße ö</div><a href="x">l</a>');

        expect($result)->toContain('&uuml;')
            ->and($result)->toContain('&szlig;')
            ->and($result)->toContain('&ouml;')
            ->and($result)->not->toContain('&Atilde;');
    });

    it('does not leave the injected XML processing instruction in the output', function () {
        $result = $this->filter->linkFilter('<div>ö</div><a href="x">l</a>');

        expect($result)->not->toContain('<?xml');
    });

    it('adds safe rel and target attributes to links', function () {
        $result = $this->filter->linkFilter('<a href="https://example.com">link</a>');

        expect($result)->toContain('rel="noopener noreferrer"')
            ->and($result)->toContain('target="_blank"');
    });

    it('returns input untouched when no link is present (fast path)', function () {
        $input = '<div>Grüße ö</div>';

        expect($this->filter->linkFilter($input))->toBe($input);
    });
});

describe('Mage_Core_Model_Input_Filter_MaliciousCode::filterPreservingDirectives', function () {
    beforeEach(function () {
        $this->filter = new Mage_Core_Model_Input_Filter_MaliciousCode();
    });

    it('preserves a directive whose nested quotes are invalid HTML attribute syntax', function () {
        // {{media url="..."}} inside an img src is what breaks under a plain filter() call:
        // the inner quotes end the attribute, and the directive comes back URL-encoded.
        $result = $this->filter->filterPreservingDirectives(
            '<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p>',
        );

        expect($result)->toContain('{{media url="wysiwyg/a.webp"}}')
            ->and($result)->not->toContain('%7B%7B');
    });

    it('preserves several directives independently', function () {
        $result = $this->filter->filterPreservingDirectives(
            '<p>{{store url="checkout/cart"}}</p><p>{{widget type="cms/widget_page_link"}}</p>',
        );

        expect($result)->toContain('{{store url="checkout/cart"}}')
            ->and($result)->toContain('{{widget type="cms/widget_page_link"}}');
    });

    it('still strips markup wrapped in braces that is not a directive', function () {
        $result = $this->filter->filterPreservingDirectives('{{<script>alert(document.cookie)</script>}}');

        expect($result)->not->toContain('<script');
    });

    it('still strips markup smuggled into something that looks like a directive', function () {
        // Regression: a permissive mask body ('.*?') matched {{a<script>…}} as a "directive",
        // restored it verbatim, and \Maho\Filter\Template leaves an unknown directive in the
        // output as-is — so the script reached the page. The mask body excludes < and >.
        $result = $this->filter->filterPreservingDirectives('{{a<script>alert(document.cookie)</script>}}');

        expect($result)->not->toContain('<script');
    });

    it('still strips malicious markup around a preserved directive', function () {
        $result = $this->filter->filterPreservingDirectives(
            '<p onclick="alert(1)">{{media url="wysiwyg/a.webp"}}</p><iframe src="//evil.test"></iframe>',
        );

        expect($result)->toContain('{{media url="wysiwyg/a.webp"}}')
            ->and($result)->not->toContain('onclick')
            ->and($result)->not->toContain('<iframe');
    });

    it('leaves links alone by default', function () {
        $result = $this->filter->filterPreservingDirectives('<a href="/checkout/cart">Cart</a>');

        expect($result)->not->toContain('target="_blank"');
    });

    it('applies the link filter when asked', function () {
        $result = $this->filter->filterPreservingDirectives('<a href="/checkout/cart">Cart</a>', true);

        expect($result)->toContain('target="_blank"')
            ->and($result)->toContain('rel="noopener noreferrer"');
    });

    it('preserves a directive even when the link filter runs', function () {
        // linkFilter() round-trips through DOMDocument, so the mask token has to survive that too.
        $result = $this->filter->filterPreservingDirectives(
            '<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p><a href="/x">l</a>',
            true,
        );

        expect($result)->toContain('{{media url="wysiwyg/a.webp"}}');
    });

    it('handles null and empty content', function () {
        expect($this->filter->filterPreservingDirectives(null))->toBe('')
            ->and($this->filter->filterPreservingDirectives(''))->toBe('');
    });
});

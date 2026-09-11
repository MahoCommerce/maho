<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Security\SvgAllowlist;
use Maho\Security\SvgDocumentSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

uses(Tests\MahoBackendTestCase::class);

/**
 * Sanitize $svg the way an upload does, and return the file content afterwards.
 * Returns null when the validator rejects the file.
 */
function sanitizeUpload(string $svg): ?string
{
    $path = tempnam(sys_get_temp_dir(), 'svgtest');
    file_put_contents($path, $svg);

    try {
        (new Mage_Core_Model_File_Validator_Svg())->validate($path);
        return (string) file_get_contents($path);
    } catch (Throwable) {
        return null;
    } finally {
        @unlink($path);
    }
}

describe('Mage_Core_Model_File_Validator_Svg', function () {
    // The validator used a blocklist, which let through every name it did not list. Each test
    // below fails against that version.
    it('refuses a file that declares an entity, which read a local file into the saved SVG', function () {
        $canary = tempnam(sys_get_temp_dir(), 'canary');
        file_put_contents($canary, 'SECRET-CANARY-VALUE');

        $result = sanitizeUpload(
            '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file://' . $canary . '">]>'
            . '<svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>',
        );

        expect($result)->toBeNull();

        @unlink($canary);
    });

    it('removes an animation that targets a link or an event handler', function (string $svg) {
        expect(sanitizeUpload($svg))->not->toMatch('/attributeName="(href|xlink:href|on[a-z]+)"/i');
    })->with([
        '<svg xmlns="http://www.w3.org/2000/svg"><a><animate attributeName="href" values="javascript:alert(1)"/></a></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><set attributeName="onload" to="alert(1)"/></svg>',
    ]);

    // The old check compared the text "xlink:href". A file chooses its own prefixes, so it could
    // bind the same namespace to any other name. The element below is on the allowlist, so the
    // test reaches the attribute loop instead of the element loop.
    it('removes a namespaced attribute whatever prefix the file gives it', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xl="http://www.w3.org/1999/xlink">'
            . '<linearGradient id="a" xl:href="https://evil.example/g.svg#g"/></svg>',
        ))->not->toContain('evil.example');
    });

    // The attribute iterator keys a node by its local name. A file that carries both names made
    // one node disappear from the list, and the sanitizer then never read it.
    it('removes a namespaced attribute that shares a local name with a plain one', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">'
            . '<linearGradient id="a" xlink:href="https://evil.example/g.svg#g" href="#b"/></svg>',
        ))->not->toContain('evil.example');
    });

    // A browser that cannot resolve the reference paints the color that follows it. The value is
    // local, so the whole attribute must survive.
    it('keeps a paint value that names a local reference and a fallback color', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" fill="url(#a) red"/></svg>',
        ))->toContain('fill="url(#a) red"');
    });

    it('removes an element that reads another document', function (string $svg) {
        expect(sanitizeUpload($svg))->not->toContain('evil.example');
    })->with([
        '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="https://evil.example/x.svg#a"/></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><image href="https://evil.example/x"/></svg>',
    ]);

    it('removes an element that runs code', function (string $svg) {
        expect(sanitizeUpload($svg))->not->toMatch('/<(script|style|foreignObject)\b/i');
    })->with([
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><path d="M0 0"/></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><style>@import"//evil.example"</style></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><b>h</b></foreignObject></svg>',
    ]);

    it('removes a processing instruction, which can load a stylesheet', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg"><?xml-stylesheet href="//evil.example/x.css"?><path d="M0 0"/></svg>',
        ))->not->toContain('xml-stylesheet');
    });

    it('keeps a real icon whole', function () {
        $result = sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor">'
            . '<title>Star</title><path d="M12 2l3 7h7"/></svg>',
        );

        expect($result)
            ->toContain('d="M12 2l3 7h7"')
            ->toContain('stroke="currentColor"')
            // A file is read as XML, so <title> is safe here even though inline SVG cannot keep it.
            ->toContain('<title>Star</title>');
    });

    // Only an internal subset can declare an entity. An export tool writes a plain public
    // identifier into every file, and libxml never reads the document it names.
    it('keeps a file whose document type declares no entity', function () {
        expect(sanitizeUpload(
            '<?xml version="1.0"?><!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" '
            . '"http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2l3 7"/></svg>',
        ))->toContain('d="M12 2l3 7"');
    });

    // The element allowlist ignores letter case, so the value rule must ignore it too. Otherwise
    // an unusual spelling reaches the element and no rule ever reads its value.
    it('removes a paint value whatever letter case the file gives the attribute', function (string $svg) {
        expect(sanitizeUpload($svg))->not->toContain('evil.example');
    })->with([
        '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5" FILL="url(http://evil.example/x#g)"/></svg>',
        '<svg xmlns="http://www.w3.org/2000/svg"><circle r="5" Clip-Path="url(http://evil.example/x#c)"/></svg>',
    ]);

    // No document type reaches the tree, so nothing declares the name. The saved file would hold
    // `&xxe;`, and a browser refuses to read it.
    it('refuses a file that uses an entity it does not declare', function () {
        expect(sanitizeUpload(
            '<!DOCTYPE svg SYSTEM "http://127.0.0.1:1/x.dtd">'
            . '<svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>',
        ))->toBeNull();
    });

    it('keeps the fill mode of an animation', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="opacity" dur="1s" fill="freeze"/></svg>',
        ))->toContain('fill="freeze"');
    });

    it('keeps an animation that only moves the graphic', function () {
        $result = sanitizeUpload((string) file_get_contents(
            Mage::getBaseDir() . '/public/skin/frontend/base/default/images/loading.svg',
        ));

        expect($result)
            ->toContain('attributeName="transform"')
            ->toContain('repeatCount="indefinite"');
    });
});

describe('Maho\Security\SvgDocumentSanitizer', function () {
    // The XML half of the policy answers the interface that the HTML half answers, so a reader
    // sees two instances of one idea, and a test reaches it without a file on disk.
    it('answers the interface that the content sanitizer answers', function () {
        expect(new SvgDocumentSanitizer())->toBeInstanceOf(HtmlSanitizerInterface::class);

        // Mage_Core_Helper_Purifier takes that interface, so either half fits the same seam.
        $seam = (new ReflectionMethod(Mage_Core_Helper_Purifier::class, '__construct'))->getParameters()[0];

        expect((string) $seam->getType())->toContain(HtmlSanitizerInterface::class);
    });

    it('removes the same markup that the content sanitizer removes', function () {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script>'
            . '<path d="M0 0" fill="url(https://evil.test/x)" onclick="alert(1)"/></svg>';

        expect((new SvgDocumentSanitizer())->sanitize($svg))
            ->not->toContain('script')
            ->not->toContain('evil.test')
            ->not->toContain('onclick')
            ->toContain('d="M0 0"');
    });

    it('refuses a document that it cannot make safe', function (string $svg) {
        expect(fn() => (new SvgDocumentSanitizer())->sanitize($svg))->toThrow(RuntimeException::class);
    })->with([
        '<!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"/>',
        '<!DOCTYPE svg SYSTEM "http://127.0.0.1:1/x.dtd"><svg xmlns="http://www.w3.org/2000/svg"><text>&xxe;</text></svg>',
        'not xml at all',
        '<script xmlns="http://www.w3.org/2000/svg">alert(1)</script>',
        '<html xmlns="http://www.w3.org/1999/xhtml"><body/></html>',
    ]);

    // Only Symfony's own UrlAttributeSanitizer removes this one, and the config is the only place
    // that holds it. A path that read the Maho filters alone would keep the link.
    it('runs the value filters that only the config carries', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="https:/evil.test"><rect width="1" height="1"/></a></svg>',
        ))->not->toContain('evil.test');
    });

    // That filter rewrites a value, which no Maho filter does. DOMAttr::$value reads an entity
    // reference, so the rewritten query string would come back empty through that setter.
    it('keeps a rewritten link whole', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="https://shop.example/x?q=red shoes&amp;cat=3">'
            . '<rect width="1" height="1"/></a></svg>',
        ))->toContain('q=red%20shoes&amp;cat=3');
    });
});

describe('Maho\Security\SvgAllowlist', function () {
    it('never allows an element that runs code or reads another document', function () {
        foreach (SvgAllowlist::NEVER_ALLOWED as $element) {
            expect(SvgAllowlist::canonicalElement($element))->toBeNull();
        }
    });

    // An animation may change only an attribute that the author may already write. Without this,
    // an allowed animation element could point at a link or an event handler.
    it('never lets an animation target a link, a handler or a reference', function (string $attribute) {
        expect(SvgAllowlist::animatableAttributes())->not->toContain($attribute);
    })->with(['href', 'xlink:href', 'onload', 'onclick', 'class', 'style', 'id', 'xmlns']);

    // The purifier stores every name in lower case. A browser gives SVG its standard spelling
    // again when it reads the page, but only for the names in the HTML5 tables. A name outside
    // those tables would reach the browser in lower case and draw nothing.
    it('uses only names that a browser restores from lower case', function () {
        foreach (SvgAllowlist::ELEMENTS as $element => $ignored) {
            $attributes = SvgAllowlist::attributesFor($element) ?? [];
            $lowerAttributes = implode(' ', array_map(
                fn(string $name): string => strtolower($name) . '="1"',
                $attributes,
            ));
            $lower = strtolower($element);
            $markup = $lower === 'svg'
                ? "<svg {$lowerAttributes}></svg>"
                : "<svg><defs><{$lower} {$lowerAttributes}></{$lower}></defs></svg>";

            $document = \Dom\HTMLDocument::createFromString('<body>' . $markup, LIBXML_NOERROR);

            $node = null;
            foreach ($document->getElementsByTagName('*') as $candidate) {
                if (strcasecmp($candidate->nodeName, $element) === 0 && $candidate->attributes->length > 0) {
                    $node = $candidate;
                }
            }

            expect($node?->nodeName)->toBe($element);

            $restored = [];
            foreach ($node->attributes as $attribute) {
                $restored[strtolower($attribute->nodeName)] = $attribute->nodeName;
            }
            foreach ($attributes as $name) {
                expect($restored[strtolower($name)] ?? null)->toBe($name);
            }
        }
    })->skip(PHP_VERSION_ID < 80400, 'Dom\HTMLDocument needs PHP 8.4');

    it('gives the editor every element that a save keeps, and nothing a save drops', function () {
        $editor = array_keys(SvgAllowlist::forEditor());

        expect($editor)->toBe(array_map(strtolower(...), [
            ...array_keys(SvgAllowlist::ELEMENTS),
            ...array_keys(SvgAllowlist::BASELINE_ELEMENTS),
        ]))
            // <title> works in a file and cannot work inline, so the editor must not claim it.
            ->and($editor)->not->toContain('title')
            // A file keeps it, so the one list must still hold it.
            ->and(SvgAllowlist::elementNames())->toContain('title');
    });

    // The editor builds the live graphic with the SVG DOM, which reads a name letter by letter.
    // A lower case name there draws nothing, so each entry carries the standard spelling.
    it('gives the editor the standard spelling of every name', function () {
        $editor = SvgAllowlist::forEditor();

        expect($editor['lineargradient']['name'])->toBe('linearGradient')
            ->and($editor['svg']['attributes']['viewbox'])->toBe('viewBox')
            ->and($editor['animatetransform']['name'])->toBe('animateTransform');
    });

    // The W3C baseline owns the attributes of an HTML element, and a save gives <a> none of the
    // SVG paint attributes. The editor and the file must not grant them either, or one graphic
    // draws two ways depending on how it reached the store.
    it('gives a link no attribute that a save removes from it', function () {
        expect(SvgAllowlist::attributesFor('a'))
            ->not->toContain('fill')
            ->not->toContain('stroke')
            ->not->toContain('transform')
            ->toContain('href')
            ->toContain('class');
    });

    it('gives the editor the attributes that every path allows', function () {
        $svg = SvgAllowlist::forEditor()['svg']['attributes'];

        foreach (SvgAllowlist::GLOBAL_ATTRIBUTES as $attribute) {
            expect($svg[$attribute] ?? null)->toBe($attribute);
        }
    });

    // The point of one policy: a graphic keeps the same markup whichever way it reaches the store.
    it('keeps the same markup in an upload that a content field keeps', function (string $svg) {
        $upload = (string) sanitizeUpload($svg);
        $content = (string) Mage::helper('core/purifier')->purify($svg);

        foreach (['class="logo"', 'style="fill:#e11d48"', 'data-part="mark"', 'aria-label="Logo"'] as $kept) {
            expect($upload)->toContain($kept);
            expect($content)->toContain($kept);
        }
    })->with([
        '<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0" class="logo" style="fill:#e11d48" '
        . 'data-part="mark" aria-label="Logo"/></svg>',
    ]);

    // The link rule lives in Mage_Core_Model_Input_Filter_SvgLink, which both paths run from one
    // list. A file needs it most: it has no W3C baseline behind it, and media/ serves it from the
    // origin of the store.
    it('keeps a link in an uploaded file but only to a page', function (string $href, bool $kept) {
        $result = (string) sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg"><a href="' . $href . '"><rect width="1" height="1"/></a></svg>',
        );

        expect(str_contains($result, 'href'))->toBe($kept);
    })->with([
        ['/sale', true],
        ['https://ok.test/x', true],
        ['//cdn.example/x', true],
        ['mailto:a@b.test', true],
        ['tel:+123', true],
        // The first colon follows a slash, so it opens no scheme.
        ['/a:b', true],
        ['javascript:alert(1)', false],
        ['JaVaScRiPt:alert(1)', false],
        ['data:text/html,x', false],
        ['vbscript:msgbox', false],
        // parse_url() gives up on each of these and reports the failure the way it reports a
        // relative value. A browser reads the scheme and runs the line after the comment.
        ['javascript:///%0Aalert(1)', false],
        ['javascript://?%0Aalert(1)', false],
        ['javascript://#%0Aalert(1)', false],
        // The XML parser resolves the reference, so the value arrives as the payload above.
        ['java&#115;cript:///%0Aalert(1)', false],
    ]);
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Security\SvgAllowlist;

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
    // bind the same namespace to any other name.
    it('removes a namespaced attribute whatever prefix the file gives it', function () {
        expect(sanitizeUpload(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xl="http://www.w3.org/1999/xlink">'
            . '<a xl:href="javascript:alert(1)"><text>go</text></a></svg>',
        ))->not->toContain('javascript:');
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

    it('keeps an animation that only moves the graphic', function () {
        $result = sanitizeUpload((string) file_get_contents(
            Mage::getBaseDir() . '/public/skin/frontend/base/default/images/loading.svg',
        ));

        expect($result)
            ->toContain('attributeName="transform"')
            ->toContain('repeatCount="indefinite"');
    });
});

describe('Maho\Security\SvgAllowlist', function () {
    it('never allows an element that runs code or reads another document', function () {
        foreach (SvgAllowlist::NEVER_ALLOWED as $element) {
            expect(SvgAllowlist::canonicalElement($element, includeFileOnly: true))->toBeNull();
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
        if (PHP_VERSION_ID < 80400) {
            expect(true)->toBeTrue();
            return;
        }

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
    });

    it('gives the editor the same element names that the purifier allows', function () {
        expect(array_keys(SvgAllowlist::forEditor()))
            ->toBe(array_map(strtolower(...), SvgAllowlist::elementNames()));
    });
});

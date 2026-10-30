<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

// The expected values follow the resolution of a relative reference in RFC 3986, section 5.
describe('the relative URLs of a copied theme stylesheet', function () {
    $from = 'https://shop.test/skin/frontend/base/default/css/';
    $to = 'https://shop.test/media/theme/';

    it('points at the same file from the new directory', function (string $css, string $expected) use ($from, $to): void {
        expect(Mage::getModel('core/design_content_bundle')->rebaseUrls($css, $from, $to))->toBe($expected);
    })->with([
        'unquoted' => ['a{background:url(../fonts/a.woff2)}', 'a{background:url(../../skin/frontend/base/default/fonts/a.woff2)}'],
        'double quotes' => ['a{src:url("../fonts/a.woff2") format("woff2")}', 'a{src:url("../../skin/frontend/base/default/fonts/a.woff2") format("woff2")}'],
        'single quotes' => ["a{src:url('../images/a.png')}", "a{src:url('../../skin/frontend/base/default/images/a.png')}"],
        'a parent theme' => ['a{src:url(../../../acme/default/images/a.png)}', 'a{src:url(../../skin/frontend/acme/default/images/a.png)}'],
        'a file in the same directory' => ['a{src:url(a.png)}', 'a{src:url(../../skin/frontend/base/default/css/a.png)}'],
        'a query and a fragment' => ['a{src:url(../fonts/a.eot?#iefix)}', 'a{src:url(../../skin/frontend/base/default/fonts/a.eot?#iefix)}'],
        'two URLs' => ['a{b:url(../a.png)}c{d:url(../b.png)}', 'a{b:url(../../skin/frontend/base/default/a.png)}c{d:url(../../skin/frontend/base/default/b.png)}'],
    ]);

    it('leaves a URL that does not depend on the directory', function (string $css) use ($from, $to): void {
        expect(Mage::getModel('core/design_content_bundle')->rebaseUrls($css, $from, $to))->toBe($css);
    })->with([
        'a data URL' => ['a{b:url("data:image/svg+xml,%3Csvg width=\'1\'%3E%3C/svg%3E")}'],
        'a data URL with a closing parenthesis' => ['a{b:url("data:image/svg+xml,<svg><path d=\'M0 0)\'/></svg>")}'],
        'an absolute URL' => ['a{b:url(https://cdn.test/a.woff2)}'],
        'a protocol-relative URL' => ['a{b:url(//cdn.test/a.woff2)}'],
        'a path from the root' => ['a{b:url(/skin/a.png)}'],
        'a fragment' => ['a{b:url(#a)}'],
        'no URL' => ['a{b:red}'],
    ]);

    it('gives a URL with the host of the skin when the new file is on another host', function (): void {
        $css = Mage::getModel('core/design_content_bundle')->rebaseUrls(
            'a{src:url(../fonts/a.woff2)}',
            'https://shop.test/skin/frontend/base/default/css/',
            'https://cdn.test/theme/',
        );

        expect($css)->toBe('a{src:url(//shop.test/skin/frontend/base/default/fonts/a.woff2)}');
    });

    it('keeps the path of a store in a subdirectory', function (): void {
        $css = Mage::getModel('core/design_content_bundle')->rebaseUrls(
            'a{src:url(../fonts/a.woff2)}',
            'https://shop.test/maho/skin/frontend/base/default/css/',
            'https://shop.test/maho/media/theme/',
        );

        expect($css)->toBe('a{src:url(../../skin/frontend/base/default/fonts/a.woff2)}');
    });
});

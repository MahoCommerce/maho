<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function acceptHelper(): Maho_ContentNegotiation_Helper_Data
{
    /** @var Maho_ContentNegotiation_Helper_Data $helper */
    $helper = Mage::helper('contentnegotiation');
    return $helper;
}

describe('Accept header', function () {
    test('selects markdown only when it outranks html', function (string $accept, bool $expected) {
        expect(acceptHelper()->acceptsMarkdown($accept))->toBe($expected);
    })->with([
        'markdown only' => ['text/markdown', true],
        'markdown before html' => ['text/markdown, text/html;q=0.9', true],
        'markdown above wildcard' => ['text/markdown, */*;q=0.8', true],
        'html preferred' => ['text/html, text/markdown;q=0.1', false],
        'equal quality' => ['text/html, text/markdown', false],
        'text wildcard' => ['text/*', false],
        'any wildcard' => ['*/*', false],
        'browser default' => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false],
        'empty' => ['', false],
    ]);
});

describe('routes', function () {
    test('reads one prefix per line and ignores blank lines', function () {
        Mage::app()->getStore()->setConfig(Maho_ContentNegotiation_Helper_Data::XML_PATH_ALLOWED_ROUTES, " catalog/product/view \n\ncms/page/view\n");

        expect(acceptHelper()->getAllowedRoutes())->toBe(['catalog/product/view', 'cms/page/view']);
    });

    test('ships the catalog, cms and blog pages by default', function () {
        expect(acceptHelper()->getAllowedRoutes())->toContain('catalog/product/view', 'catalog/category/view', 'cms/page/view', 'cms/index/index', 'blog/index/view');
    });
});

describe('markdown url', function () {
    test('replaces a trailing slash and keeps the query string', function () {
        expect(acceptHelper()->toMarkdownUrl('https://store.test/category/?p=2'))->toBe('https://store.test/category.md?p=2');
        expect(acceptHelper()->toMarkdownUrl('https://store.test/product.html'))->toBe('https://store.test/product.html.md');
    });
});

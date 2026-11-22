<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

beforeEach(function (): void {
    $store = Mage::app()->getStore();
    $store->setConfig('dev/css/minify_enabled', '0');
    $store->setConfig('dev/js/minify_enabled', '0');
});

function headStylesheetOrder(string $html): array
{
    preg_match_all('#/(\w+)\.css[?"]#', $html, $matches);
    return $matches[1];
}

function headTagOf(string $html, string $file): string
{
    preg_match('#<[^>]*/' . preg_quote($file, '#') . '[?"][^>]*>#', $html, $match);
    return $match[0] ?? '';
}

it('renders a stylesheet added before all items first, whatever the params of the other items', function (): void {
    $html = Mage::app()->getLayout()->createBlock('page/html_head')
        ->addCss('css/a.css')
        ->addCss('css/b.css', 'media="print"')
        ->addCss('css/c.css')
        ->addCss('css/first.css', 'media="print"', '*', true)
        ->getCssJsHtml();

    expect(headStylesheetOrder($html))->toBe(['first', 'a', 'b', 'c']);
});

it('keeps the params of each item', function (): void {
    $html = Mage::app()->getLayout()->createBlock('page/html_head')
        ->addCss('css/a.css')
        ->addCss('css/b.css', 'media="print"')
        ->addJs('a.js', 'defer')
        ->addJs('b.js')
        ->getCssJsHtml();

    expect(headTagOf($html, 'a.css'))->toContain('media="all"')
        ->and(headTagOf($html, 'b.css'))->toContain('media="print"')
        ->and(headTagOf($html, 'a.js'))->toContain(' defer')
        ->and(headTagOf($html, 'b.js'))->not->toContain('defer');
});

it('treats empty params and cond elements in layout XML as not set', function (): void {
    $layout = Mage::getModel('core/layout');
    $layout->loadString(
        '<layout><block type="page/html_head" name="head">'
        . '<action method="addCss"><stylesheet>css/a.css</stylesheet></action>'
        . '<action method="addItem"><type>skin_css</type><name>css/first.css</name>'
        . '<params/><cond/><referenceName>*</referenceName><before>true</before></action>'
        . '</block></layout>',
    );
    $layout->generateBlocks();
    $html = $layout->getBlock('head')->getCssJsHtml();

    expect(headStylesheetOrder($html))->toBe(['first', 'a'])
        ->and(headTagOf($html, 'first.css'))->toContain('media="all"');
});

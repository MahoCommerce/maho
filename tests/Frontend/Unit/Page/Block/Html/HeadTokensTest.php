<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function renderHeadWithTokens(array $values = []): string
{
    $store = Mage::app()->getStore();
    $store->setConfig(Mage_Core_Model_Design_Tokens::CUSTOM_CSS_PATH, '');
    $store->setConfig(Mage_Core_Model_Design_Tokens::FONT_STYLESHEET_PATH, '');
    foreach (Mage::getConfig()->getNode(Mage_Core_Model_Design_Tokens::CONFIG_NODE)->children() as $entry) {
        $store->setConfig(trim((string) $entry->path), '');
    }
    foreach ($values as $name => $value) {
        $store->setConfig('design/tokens/' . $name, $value);
    }

    // The head block links its fonts in _construct(), so build it after the config is set,
    // and on the default theme so no theme font declaration leaks into the assertions
    Mage::getDesign()->setTheme('default');
    return Mage::app()->getLayout()->createBlock('page/html_head')
        ->addCss('css/styles.css')
        ->setTemplate('page/html/head.phtml')
        ->toHtml();
}

it('renders the token style after every stylesheet, so it beats theme.css', function () {
    $html = renderHeadWithTokens(['color_primary' => '#0e7a5f']);

    expect($html)->toContain('<style id="design-tokens">');
    expect(strpos($html, '<style id="design-tokens">'))->toBeGreaterThan(strrpos($html, 'rel="stylesheet"'));
});

it('renders no style element while every field is empty', function () {
    expect(renderHeadWithTokens())->not->toContain('<style id="design-tokens">');
});

it('links the configured stylesheet and derives its preconnect', function () {
    $html = renderHeadWithTokens(['font_stylesheet' => 'https://fonts.bunny.net/css2?family=Karla&display=swap']);

    expect($html)->toContain('rel="preconnect" crossorigin href="https://fonts.bunny.net"')
        ->and($html)->toContain('family=Karla');
});

it('ignores a stylesheet URL that is not http or https', function (string $url) {
    expect(renderHeadWithTokens(['font_stylesheet' => $url]))->not->toContain('rel="preconnect"');
})->with([
    ['javascript:alert(1)'],
    ['//fonts.bunny.net/css2?family=Karla'],
    ['not a url'],
]);

it('keeps the preview chrome rule out of the storefront itself', function () {
    // The rule is injected by the admin preview only: a shopper's page never carries it
    expect(renderHeadWithTokens(['color_primary' => '#0e7a5f']))->not->toContain('data-preview-hide');
});

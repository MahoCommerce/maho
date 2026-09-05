<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function websiteSwitcherHtml(): string
{
    return Mage::app()->getLayout()
        ->createBlock('page/switch_website')
        ->setTemplate('page/switch/websites.phtml')
        ->toHtml();
}

beforeEach(fn() => Mage::register('isSecureArea', true, true));
afterEach(function (): void {
    deletePriceWebsite('imp_switch');
    Mage::unregister('isSecureArea');
});

it('renders nothing with a single website', function (): void {
    if (count(Mage::app()->getWebsites()) > 1) {
        test()->markTestSkipped('This install already has more than one website');
    }
    expect(websiteSwitcherHtml())->toBe('');
});

it('lists every website by its default store home url and marks the current one', function (): void {
    $website = createPriceWebsite('imp_switch', 95);
    $website->setName('Imp Switch')->save();
    Mage::app()->reinitStores();

    $html = websiteSwitcherHtml();
    expect($html)->toContain('id="select-website"');
    expect($html)->toContain('>Imp Switch<');
    expect($html)->toContain(' selected="selected">' . Mage::app()->getWebsite()->getName() . '<');
    expect($html)->toContain(Mage::app()->getStore('imp_switch')->getUrl(''));
    expect(substr_count($html, '<option '))->toBe(count(Mage::app()->getWebsites()));
});

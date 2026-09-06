<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function rootAttributesWithDarkMode(string $value): string
{
    Mage::app()->getStore()->setConfig(Mage_Core_Model_Design_Tokens::DARK_MODE_PATH, $value);
    return Mage::app()->getLayout()->createBlock('page/html')->getRootAttributes();
}

it('adds no root attribute while dark mode follows the device', function () {
    expect(rootAttributesWithDarkMode('1'))->toBe('');
});

it('pins the light color scheme on the root when dark mode is off', function () {
    expect(rootAttributesWithDarkMode('0'))->toBe(' data-color-scheme="light"');
});

it('renders the attribute on the html element of every page template', function () {
    Mage::app()->getStore()->setConfig(Mage_Core_Model_Design_Tokens::DARK_MODE_PATH, '0');
    foreach (['1column', '2columns-left', '2columns-right', '3columns', 'empty', 'popup'] as $template) {
        $html = Mage::app()->getLayout()->createBlock('page/html')->setTemplate("page/$template.phtml")->toHtml();
        expect(str_contains($html, '<html lang="en" data-color-scheme="light">'))->toBeTrue("$template.phtml lacks the attribute");
    }
});

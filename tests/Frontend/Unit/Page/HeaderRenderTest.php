<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * The header holds the logo, the currency switcher and the mini cart, and it is the
 * only block that renders them. Mage_Core_Block_Template::fetchView() swallows a
 * render error outside developer mode, so a broken header serves a page that is
 * merely missing its whole top, with no failing request to notice.
 *
 * Wave 8 of #1283 broke it that way: getLogoWidth() promises a string and returned
 * the Mage_Core_Model_Security_HtmlEscapedString that escapeHtmlAsObject() stores,
 * which the engine coerced until the file declared strict types.
 */

it('renders the page header with its logo', function () {
    $block = Mage::app()->getLayout()->createBlock('page/html_header');
    $block->setTemplate('page/html/header.phtml');

    $html = $block->toHtml();

    expect($html)->toContain('page-header-container')
        ->and($html)->toContain('<img');
});

it('reads the logo size as a string', function () {
    $block = Mage::app()->getLayout()->createBlock('page/html_header');

    expect($block->getLogoWidth())->toBeString()
        ->and($block->getLogoHeight())->toBeString()
        ->and($block->getLogoSrc())->toBeString();
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function headerLogoBlock(string $src): Mage_Page_Block_Html_Header
{
    $block = Mage::app()->getLayout()->createBlock('page/html_header');
    $block->setLogo($src, 'Demo');
    return $block;
}

it('resolves a skin path, a media path and a full URL for the logo', function (): void {
    expect(headerLogoBlock('images/logo.svg')->getLogoSrc())->toEndWith('/images/logo.svg')->toContain('/skin/');
    expect(headerLogoBlock('media/wysiwyg/food/logo.svg')->getLogoSrc())
        ->toBe(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'wysiwyg/food/logo.svg');
    expect(headerLogoBlock('https://cdn.example.com/logo.svg')->getLogoSrc())->toBe('https://cdn.example.com/logo.svg');
});

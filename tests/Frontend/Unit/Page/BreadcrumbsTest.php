<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

function breadcrumbsBlock(array $crumbs): Mage_Page_Block_Html_Breadcrumbs
{
    /** @var Mage_Page_Block_Html_Breadcrumbs $block */
    $block = Mage::app()->getLayout()->createBlock('page/html_breadcrumbs');
    foreach ($crumbs as $name => $info) {
        $block->addCrumb($name, $info);
    }
    return $block;
}

it('drops the unlinked current page and marks the new ends of the trail', function () {
    $block = breadcrumbsBlock([
        'home' => ['label' => 'Home', 'link' => 'https://example.test/'],
        'category' => ['label' => 'Shoes', 'link' => 'https://example.test/shoes'],
        'product' => ['label' => 'Runner'],
    ]);

    $visible = $block->getVisibleCrumbs();
    expect(array_keys($visible))->toBe(['home', 'category'])
        ->and($visible['home']['first'])->toBeTrue()
        ->and($visible['category']['last'])->toBeTrue()
        ->and($block->getCrumbs())->toHaveCount(3);
});

it('shows nothing when only Home would remain', function () {
    $block = breadcrumbsBlock([
        'home' => ['label' => 'Home', 'link' => 'https://example.test/'],
        'cms_page' => ['label' => 'About Us'],
    ]);

    expect($block->getVisibleCrumbs())->toBe([])
        ->and($block->toHtml())->toBe('');
});

it('keeps a trail that ends in a link', function () {
    $block = breadcrumbsBlock([
        'home' => ['label' => 'Home', 'link' => 'https://example.test/'],
        'category' => ['label' => 'Shoes', 'link' => 'https://example.test/shoes'],
    ]);

    expect(array_keys($block->getVisibleCrumbs()))->toBe(['home', 'category']);
});

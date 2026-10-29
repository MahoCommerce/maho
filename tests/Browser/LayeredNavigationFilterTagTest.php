<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

it('keeps a long active filter value inside its tag in the sidebar', function () {
    $rootId = Mage::app()->getDefaultStoreView()->getRootCategoryId();
    $categoryId = (int) Mage::getResourceModel('catalog/category_collection')
        ->addAttributeToFilter('path', ['like' => "1/{$rootId}/%"])
        ->addAttributeToFilter('is_anchor', 1)
        ->addIsActiveFilter()
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    expect($categoryId)->toBeGreaterThan(0);

    // The price filter always adds an active filter tag, so the test needs no option of the sample data
    $page = visit(MahoServer::baseUrl() . "/catalog/category/view/{$categoryId}/?price=0-100000");
    waitForPageLoad($page, '.filter-tag-link');

    $page->script("document.querySelector('.filter-tag-value').textContent = 'Produttori Vini Manduria Produttori Vini Manduria'");

    $tag = "document.querySelector('.filter-tag-link')";
    $value = "document.querySelector('.filter-tag-value')";
    expect($page->script("{$value}.offsetHeight > 1.5 * parseFloat(getComputedStyle({$value}).lineHeight)"))->toBeTrue()
        ->and($page->script("{$tag}.scrollHeight <= {$tag}.clientHeight"))->toBeTrue()
        ->and($page->script("{$value}.getBoundingClientRect().bottom <= {$tag}.getBoundingClientRect().bottom"))->toBeTrue();
});

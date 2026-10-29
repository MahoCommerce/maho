<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

const BUY_BOX_BUTTON = "document.querySelector('.btn-cart').getBoundingClientRect()";
const BUY_BOX_QTY = "document.querySelector('.qty-wrapper').getBoundingClientRect()";
const BUY_BOX_LINKS = "document.querySelector('.add-to-links').getBoundingClientRect()";
const BUY_BOX = "document.querySelector('.btn-cart').closest('.add-to-box, .product-options-bottom').getBoundingClientRect()";

/**
 * The URL of a saleable product of type $typeId.
 */
function buyBoxProductUrl(string $typeId): string
{
    $products = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', $typeId)
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->setPageSize(1);
    if ($typeId === 'simple') {
        $products->addAttributeToFilter('price', ['gt' => 0]);
    }
    Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($products);

    return MahoServer::baseUrl() . '/catalog/product/view/id/' . (int) $products->getFirstItem()->getId() . '/';
}

/**
 * Wait for the buy box, then give it $count buttons in the wishlist and compare links.
 */
function prepareBuyBox(object $page, int $count): object
{
    $page = $page->assertPresent('.add-to-links');
    waitForPageLoad($page, '.btn-cart');

    // The store config decides which links show, so the test sets their number itself
    $page->script("(() => {
        const links = document.querySelector('.add-to-links');
        while (links.children.length > {$count}) links.lastElementChild.remove();
        while (links.children.length < {$count}) links.append(links.firstElementChild.cloneNode(true));
    })()");
    expect($page->script("document.querySelector('.add-to-links').children.length"))->toBe($count);

    return $page;
}

it('shows the add to cart button at the full width below the qty and the links on a phone', function (int $count, bool $tierPrices) {
    $page = prepareBuyBox(visit(buyBoxProductUrl('simple'))->on()->mobile(), $count);

    // A product with options renders its tier prices first in the buy box
    if ($tierPrices) {
        $page->script("document.querySelector('.add-to-box').insertAdjacentHTML('afterbegin',
            '<ul class=\"tier-prices product-pricing\"><li class=\"tier-price tier-0\">Buy 5 for 10.00 each</li></ul>')");
    }

    expect($page->script('Math.round(' . BUY_BOX_BUTTON . '.width) === Math.round(' . BUY_BOX . '.width)'))->toBeTrue()
        ->and($page->script(BUY_BOX_LINKS . '.top < ' . BUY_BOX_QTY . '.bottom && ' . BUY_BOX_LINKS . '.bottom > ' . BUY_BOX_QTY . '.top'))->toBeTrue()
        ->and($page->script(BUY_BOX_BUTTON . '.top >= Math.max(' . BUY_BOX_QTY . '.bottom, ' . BUY_BOX_LINKS . '.bottom)'))->toBeTrue();
})->with([
    'wishlist' => [1, false],
    'wishlist and compare' => [2, false],
    'wishlist and tier prices' => [1, true],
]);

it('shows the add to cart button of a grouped product at the full width above the links on a small phone', function () {
    $page = prepareBuyBox(visit(buyBoxProductUrl('grouped'))->on()->iPhoneSE(), 2);

    expect($page->script('Math.round(' . BUY_BOX_BUTTON . '.width) === Math.round(' . BUY_BOX . '.width)'))->toBeTrue()
        ->and($page->script(BUY_BOX_BUTTON . '.bottom <= ' . BUY_BOX_LINKS . '.top'))->toBeTrue();
});

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

/**
 * Open a saleable simple product on a phone, with $count buttons in the wishlist and compare links.
 */
function visitBuyBoxOnPhone(int $count, bool $tierPrices): object
{
    $products = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1);
    Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($products);

    $page = visit(MahoServer::baseUrl() . '/catalog/product/view/id/' . (int) $products->getFirstItem()->getId() . '/')
        ->on()->mobile()->assertPresent('.add-to-links');
    waitForPageLoad($page, '.btn-cart');

    // The store config decides which links show, so the test sets their number itself
    $page->script("const links = document.querySelector('.add-to-links');
        while (links.children.length > {$count}) links.lastElementChild.remove();
        while (links.children.length < {$count}) links.append(links.firstElementChild.cloneNode(true));");

    // A product with options renders its tier prices first in the buy box
    if ($tierPrices) {
        $page->script("document.querySelector('.add-to-box').insertAdjacentHTML('afterbegin',
            '<ul class=\"tier-prices product-pricing\"><li class=\"tier-price tier-0\">Buy 5 for 10.00 each</li></ul>')");
    }

    return $page;
}

it('shows the add to cart button at the full width below the qty and the links on a phone', function (int $count, bool $tierPrices) {
    $page = visitBuyBoxOnPhone($count, $tierPrices);

    $box = "document.querySelector('.btn-cart').closest('.add-to-box, .product-options-bottom').getBoundingClientRect()";
    expect($page->script('Math.round(' . BUY_BOX_BUTTON . ".width) === Math.round({$box}.width)"))->toBeTrue()
        ->and($page->script(BUY_BOX_LINKS . '.top < ' . BUY_BOX_QTY . '.bottom && ' . BUY_BOX_LINKS . '.bottom > ' . BUY_BOX_QTY . '.top'))->toBeTrue()
        ->and($page->script(BUY_BOX_BUTTON . '.top >= Math.max(' . BUY_BOX_QTY . '.bottom, ' . BUY_BOX_LINKS . '.bottom)'))->toBeTrue();
})->with([
    'wishlist' => [1, false],
    'wishlist and compare' => [2, false],
    'wishlist and tier prices' => [1, true],
]);

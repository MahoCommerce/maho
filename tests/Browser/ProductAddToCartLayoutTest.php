<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Browser\MahoServer;
use Tests\MahoBrowserTestCase;

uses(MahoBrowserTestCase::class)->group('browser');

it('shows the add to cart button at the full width of the buy box on a phone', function () {
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('status', 1)
        ->addAttributeToFilter('visibility', ['in' => [3, 4]])
        ->addAttributeToFilter('price', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem();

    Mage::getModel('cataloginventory/stock_item')->loadByProduct((int) $product->getId())
        ->setData('use_config_manage_stock', 0)
        ->setData('manage_stock', 0)
        ->setData('is_in_stock', 1)
        ->save();

    $page = visit(MahoServer::baseUrl() . '/catalog/product/view/id/' . (int) $product->getId() . '/')
        ->on()->mobile()->assertPresent('.btn-cart');
    waitForPageLoad($page, '.btn-cart');

    $button = "document.querySelector('.btn-cart').getBoundingClientRect()";
    $box = "document.querySelector('.btn-cart').closest('.add-to-box, .product-options-bottom').getBoundingClientRect()";
    $qty = "document.querySelector('.qty-wrapper').getBoundingClientRect()";
    $links = "document.querySelector('.add-to-links').getBoundingClientRect()";
    expect($page->script("Math.round({$button}.width) === Math.round({$box}.width)"))->toBeTrue()
        ->and($page->script("{$links}.top < {$qty}.bottom && {$links}.bottom > {$qty}.top"))->toBeTrue();
});

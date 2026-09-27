<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

dataset('grouped renderer owners', [
    'mini-cart' => ['checkout/cart_sidebar'],
    'cart page' => ['checkout/cart'],
    'onepage review' => ['checkout/onepage_review_info'],
]);

it('returns the block that owns the grouped item renderer', function (string $ownerType) {
    $owner = Mage::app()->getLayout()->createBlock($ownerType);
    $owner->addItemRender('grouped', 'checkout/cart_item_renderer_grouped', 'checkout/cart/item/default.phtml');

    expect($owner->getItemRenderer('grouped')->getRenderedBlock())->toBe($owner);
})->with('grouped renderer owners');

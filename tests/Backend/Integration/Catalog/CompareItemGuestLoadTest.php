<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('does not load the compare item of a logged-out customer for a guest without a visitor id', function (): void {
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore('default')->getWebsiteId())
        ->setEmail('compare-guest-' . uniqid() . '@example.com')
        ->setFirstname('Compare')
        ->setLastname('Guest')
        ->save();
    $productId = (int) Mage::getModel('catalog/product')->getCollection()->getFirstItem()->getId();

    try {
        Mage::getModel('catalog/product_compare_item')
            ->setCustomerId((int) $customer->getId())
            ->setVisitorId(0)
            ->setProductId($productId)
            ->setStoreId((int) Mage::app()->getStore('default')->getId())
            ->save();

        $guestItem = Mage::getModel('catalog/product_compare_item')->setCustomerId(null)->addVisitorId(null);
        $guestItem->loadByProduct($productId);

        expect($guestItem->getId())->toBeNull();
    } finally {
        $customer->delete();
    }
});

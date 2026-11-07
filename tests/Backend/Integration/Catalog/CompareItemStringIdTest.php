<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('sets the customer id of a compare item as int when the customer id is a string', function (): void {
    $customer = Mage::getModel('customer/customer');
    $customer->setId('12');

    $item = Mage::getModel('catalog/product_compare_item');
    $item->addCustomerData($customer);

    expect($item->getCustomerId())->toBe(12);
});

it('keeps the customer id of a compare item null when the customer has no id', function (): void {
    $item = Mage::getModel('catalog/product_compare_item');
    $item->addCustomerData(Mage::getModel('customer/customer'));

    expect($item->getCustomerId())->toBeNull();
});

it('reads the visitor id of a compare item as int when the visitor id is a string', function (): void {
    Mage::getSingleton('log/visitor')->setData('visitor_id', '7');

    $item = Mage::getModel('catalog/product_compare_item');

    expect($item->getVisitorId())->toBe(7);
});

it('removes a product from the compare list when the visitor id is a string', function (): void {
    $productId = (int) Mage::getModel('catalog/product')->getCollection()->getFirstItem()->getId();
    $item = Mage::getModel('catalog/product_compare_item')
        ->setCustomerId(null)
        ->setVisitorId(987654)
        ->setProductId($productId)
        ->setStoreId((int) Mage::app()->getStore('default')->getId())
        ->save();

    try {
        Mage::getSingleton('log/visitor')->setData('visitor_id', '987654');
        Mage::getModel('catalog/product_compare_list')->removeProduct($productId);

        $check = Mage::getModel('catalog/product_compare_item')->setCustomerId(null)->addVisitorId(987654);
        $check->loadByProduct($productId);

        expect($check->getId())->toBeNull();
    } finally {
        $item->delete();
    }
});

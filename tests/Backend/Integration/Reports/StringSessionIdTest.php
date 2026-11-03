<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * A session of an older release holds the visitor id and the customer id as strings.
 * The typed setters of the models take an int only.
 */
it('reads the visitor id of a report item as int when the visitor id is a string', function (): void {
    Mage::getSingleton('log/visitor')->setData('visitor_id', '7');

    $item = Mage::getModel('reports/product_index_viewed');

    expect($item->getVisitorId())->toBe(7);
});

it('reads the customer id of the session as an int when the session holds a string', function (): void {
    $session = Mage::getSingleton('customer/session');
    $session->setData('customer_id', '12');

    try {
        expect($session->getCustomerId())->toBe(12);
    } finally {
        $session->unsetData('customer_id');
    }
});

it('reads the stored visitor id and customer id of a report item as int', function (): void {
    $item = Mage::getModel('reports/product_index_viewed');
    $item->setData('visitor_id', '7');
    $item->setData('customer_id', '12');

    expect($item->getVisitorId())->toBe(7);
    expect($item->getCustomerId())->toBe(12);
});

it('reads the customer id of a logged-in session as an int when the session holds a string', function (): void {
    $customer = Mage::getModel('customer/customer')
        ->setWebsiteId((int) Mage::app()->getStore('default')->getWebsiteId())
        ->setEmail('string-id-' . uniqid() . '@example.com')
        ->setFirstname('String')
        ->setLastname('Id')
        ->save();
    $session = Mage::getSingleton('customer/session');
    $session->setId((string) $customer->getId());

    try {
        expect($session->getCustomerId())->toBe((int) $customer->getId());
    } finally {
        $session->unsetData('id');
        $customer->delete();
    }
});

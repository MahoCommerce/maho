<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function (): void {
    $this->customers = [];
    foreach ([true, true, false] as $index => $hasWishlist) {
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(1)
            ->setStoreId(1)
            ->setFirstname('Pest')
            ->setLastname('Wishlist Count ' . $index)
            ->setEmail('pest-wishlist-count-' . $index . '-' . uniqid() . '@example.com');
        $customer->save();
        $this->customers[] = $customer;
        if ($hasWishlist) {
            Mage::getModel('wishlist/wishlist')->loadByCustomer((int) $customer->getId(), true);
        }
    }
});

afterEach(function (): void {
    Mage::register('isSecureArea', true, true);
    foreach ($this->customers as $customer) {
        $customer->delete();
    }
    Mage::unregister('isSecureArea');
});

it('counts the customers that have a wishlist', function (): void {
    $resource = Mage::getSingleton('core/resource');
    $adapter = $resource->getConnection('core_read');
    $expected = (int) $adapter->fetchOne($adapter->select()
        ->from(['wt' => $resource->getTableName('wishlist/wishlist')], [new Maho\Db\Expr('COUNT(*)')])
        ->join(['e' => $resource->getTableName('customer/entity')], 'e.entity_id = wt.customer_id', []));
    $customers = (int) $adapter->fetchOne($adapter->select()
        ->from(['e' => $resource->getTableName('customer/entity')], [new Maho\Db\Expr('COUNT(*)')]));

    [$percent, $count] = Mage::getResourceModel('reports/wishlist_collection')->getWishlistCustomerCount();

    expect($expected)->toBeGreaterThanOrEqual(2)
        ->and((int) $count)->toBe($expected)
        ->and((float) $percent)->toEqualWithDelta($expected * 100 / $customers, 0.0001);
});

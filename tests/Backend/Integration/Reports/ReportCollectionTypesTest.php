<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the name of the ID field of the product entity table', function () {
    $collection = Mage::getResourceModel('reports/product_collection');

    expect($collection->getProductEntityId())->toBe('entity_id');
});

it('joins the order items on the ID field of the product', function () {
    $collection = Mage::getResourceModel('reports/product_collection')->addOrdersCount();

    expect((string) $collection->getSelect())->toContain('order_items.product_id = e.entity_id');
});

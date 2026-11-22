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

it('gives the report collection with its date range and no page size', function () {
    $collection = Mage::getModel('reports/report')
        ->setStoreIds([])
        ->initCollection('reports/product_sold_collection')
        ->getReportFull('2026-01-01 00:00:00', '2026-01-31 23:59:59');

    expect($collection)->toBeInstanceOf(Mage_Reports_Model_Resource_Product_Sold_Collection::class)
        ->and($collection->getPageSize())->toBeNull();
});

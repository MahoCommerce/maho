<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('Bestsellers widget block', function () {
    beforeEach(function () {
        $this->block = new Mage_Reports_Block_Product_Widget_Bestsellers();
    });

    it('exposes sensible defaults', function () {
        expect($this->block->getPeriod())->toBe('all_time');
        expect($this->block->getProductsCount())->toBe(5);
        expect($this->block->onlyInStock())->toBeTrue();
    });

    it('includes the period in the cache key so different periods cache separately', function () {
        $this->block->setPeriod('last_7_days');
        expect($this->block->getCacheKeyInfo())->toContain('last_7_days');

        $this->block->setPeriod('year');
        expect($this->block->getCacheKeyInfo())->toContain('year');
    });

    it('keys the cache by store date so rolling periods refresh daily', function () {
        $today = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
        expect($this->block->getCacheKeyInfo())->toContain($today);
    });

    it('renders an empty, error-free collection when there is no sales data', function () {
        $method = new ReflectionMethod($this->block, '_getProductCollection');
        $method->setAccessible(true);
        $collection = $method->invoke($this->block);

        expect($collection)->toBeInstanceOf(Mage_Catalog_Model_Resource_Product_Collection::class);
        expect($collection->getSize())->toBe(0);
    });

    it('orders products by an explicit id list using portable SQL', function () {
        $ids = Mage::getResourceModel('catalog/product_collection')
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds())
            ->addStoreFilter()
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
            ->getAllIds();
        $ids = array_values(array_map('intval', $ids));

        if (count($ids) < 2) {
            $this->markTestSkipped('Not enough enabled products to assert ordering.');
        }

        // Reverse and trim so the requested order differs from the natural id order.
        $ids = array_slice(array_reverse($ids), 0, 5);

        $method = new ReflectionMethod($this->block, '_getOrderByIdsExpr');
        $method->setAccessible(true);
        $expr = $method->invoke($this->block, $ids);

        $collection = Mage::getResourceModel('catalog/product_collection')->addIdFilter($ids);
        $collection->getSelect()->order($expr);

        $loaded = array_values(array_map(fn($p) => (int) $p->getId(), $collection->getItems()));
        expect($loaded)->toBe($ids);
    });
});

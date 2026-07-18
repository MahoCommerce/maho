<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

describe('On Sale widget block', function () {
    beforeEach(function () {
        $this->block = new Mage_CatalogRule_Block_Product_Widget_OnSale();
    });

    it('exposes sensible defaults', function () {
        expect($this->block->getOrderMode())->toBe('newest');
        expect($this->block->getProductsCount())->toBe(5);
        expect($this->block->onlyInStock())->toBeTrue();
        expect($this->block->getRuleId())->toBeNull();
    });

    it('treats an empty rule_id as "all active rules"', function () {
        $this->block->setRuleId('');
        expect($this->block->getRuleId())->toBeNull();

        $this->block->setRuleId('7');
        expect($this->block->getRuleId())->toBe(7);
    });

    it('includes rule_id and order in the cache key', function () {
        $this->block->setRuleId(3)->setData('order', 'biggest_discount');
        $info = $this->block->getCacheKeyInfo();
        expect($info)->toContain(3);
        expect($info)->toContain('biggest_discount');
    });

    it('keys the cache by store date so it refreshes after the daily catalog-rule reindex', function () {
        $today = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT);
        expect($this->block->getCacheKeyInfo())->toContain($today);
    });

    it('renders an empty, error-free collection when no rule prices are active', function () {
        $method = new ReflectionMethod($this->block, '_getProductCollection');
        $method->setAccessible(true);
        $collection = $method->invoke($this->block);

        expect($collection)->toBeInstanceOf(Mage_Catalog_Model_Resource_Product_Collection::class);
        expect($collection->getSize())->toBe(0);
    });
});

describe('On Sale rule source model', function () {
    it('offers an "all active rules" option first', function () {
        $options = Mage::getModel('catalogrule/source_rule')->toOptionArray();
        expect($options[0]['value'])->toBe('');
        expect($options[0]['label'])->toBeString();
    });
});

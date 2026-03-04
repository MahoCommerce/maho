<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('CatalogRule Index Refresh', function () {
    beforeEach(function () {
        // Create second website + store group + store
        $this->testWebsite = Mage::getModel('core/website');
        $this->testWebsite->setCode('test_website_' . uniqid())
            ->setName('Test Website')
            ->save();

        $this->testStoreGroup = Mage::getModel('core/store_group');
        $this->testStoreGroup->setWebsiteId($this->testWebsite->getId())
            ->setName('Test Store Group')
            ->setRootCategoryId(Mage::app()->getStore()->getRootCategoryId())
            ->save();

        $this->testWebsite->setDefaultGroupId($this->testStoreGroup->getId())->save();

        $this->testStore = Mage::getModel('core/store');
        $this->testStore->setCode('test_store_' . uniqid())
            ->setWebsiteId($this->testWebsite->getId())
            ->setGroupId($this->testStoreGroup->getId())
            ->setName('Test Store')
            ->setIsActive(1)
            ->save();

        $this->testStoreGroup->setDefaultStoreId($this->testStore->getId())->save();

        // Create a catalog rule active on both websites
        $this->testRule = Mage::getModel('catalogrule/rule');
        $this->testRule->setName('Test Rule')
            ->setIsActive(1)
            ->setWebsiteIds([1, $this->testWebsite->getId()])
            ->setCustomerGroupIds([0, 1])
            ->setSimpleAction('by_percent')
            ->setDiscountAmount(10)
            ->setSortOrder(0)
            ->setConditionsSerialized(json_encode([
                'type' => Mage_CatalogRule_Model_Rule_Condition_Combine::class,
                'attribute' => null,
                'operator' => null,
                'value' => '1',
                'is_value_processed' => null,
                'aggregator' => 'all',
            ]))
            ->save();

        // Create a simple product on both websites
        $this->testProduct = Mage::getModel('catalog/product');
        $this->testProduct->setTypeId('simple')
            ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
            ->setSku('TEST-RULE-' . uniqid())
            ->setName('Test Rule Product')
            ->setPrice(100.00)
            ->setStatus(1)
            ->setVisibility(4)
            ->setWebsiteIds([1, $this->testWebsite->getId()])
            ->save();
    });

    afterEach(function () {
        // Clean up catalogrule index tables first to avoid FK constraint violations
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $websiteId = $this->testWebsite?->getId();
        if ($websiteId) {
            $write->delete($resource->getTableName('catalogrule/rule_product_price'), ['website_id = ?' => $websiteId]);
            $write->delete($resource->getTableName('catalogrule/rule_product'), ['website_id = ?' => $websiteId]);
        }

        $this->testProduct?->delete();
        $this->testRule?->delete();
        $this->testStore?->delete();
        $this->testStoreGroup?->delete();
        $this->testWebsite?->delete();
    });

    it('reindexes catalog rules across multiple websites without error', function () {
        $resource = Mage::getResourceSingleton('catalogrule/rule');
        $resource->applyAllRules();
        expect(true)->toBeTrue();
    });
});

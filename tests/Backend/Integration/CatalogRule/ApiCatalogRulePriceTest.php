<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * public/rest.php loads the events of the global and api areas only. Each test loads the api area
 * the same way, so a catalog rule listener that is missing from that area makes the test fail.
 */

const API_RULE_NAME = 'pest-api-catalog-rule-price';

function apiRuleWrite(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function apiRuleTable(string $name): string
{
    return Mage::getSingleton('core/resource')->getTableName($name);
}

beforeEach(function (): void {
    Mage::app()->loadAreaPart(Mage_Core_Model_App_Area::AREA_API, Mage_Core_Model_App_Area::PART_EVENTS);

    $sku = 'pest-api-rule-' . uniqid();
    $product = Mage::getModel('catalog/product')
        ->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku($sku)
        ->setName('Pest API rule product')
        ->setPrice(40.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)
        ->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100]);
    $product->save();
    $this->productId = (int) $product->getId();

    $rule = Mage::getModel('catalogrule/rule');
    $rule->setName(API_RULE_NAME)
        ->setIsActive(true)
        ->setWebsiteIds([1])
        ->setCustomerGroupIds([0, 1])
        ->setSimpleAction('by_percent')
        ->setDiscountAmount(50)
        ->setStopRulesProcessing(false);
    $rule->loadPost(['conditions' => [
        '1' => ['type' => 'catalogrule/rule_condition_combine', 'aggregator' => 'all', 'value' => '1', 'new_child' => ''],
        '1--1' => ['type' => 'catalogrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $sku],
    ]]);
    $rule->save();

    Mage::getModel('catalogrule/rule')->applyAllRulesToProduct($this->productId);
});

afterEach(function (): void {
    $write = apiRuleWrite();
    $ruleIds = $write->fetchCol(
        $write->select()->from(apiRuleTable('catalogrule/rule'), 'rule_id')->where('name = ?', API_RULE_NAME),
    );
    if ($ruleIds) {
        $write->delete(apiRuleTable('catalogrule/rule_product'), ['rule_id IN (?)' => $ruleIds]);
        $write->delete(apiRuleTable('catalogrule/rule'), ['rule_id IN (?)' => $ruleIds]);
    }
    $write->delete(apiRuleTable('catalogrule/rule_product_price'), ['product_id = ?' => $this->productId]);
    $write->delete(apiRuleTable('catalog/product'), ['entity_id = ?' => $this->productId]);
});

it('prices a cart line with the catalog rule price', function (): void {
    $product = Mage::getModel('catalog/product')->setStoreId(1)->load($this->productId);

    $quote = Mage::getModel('sales/quote')
        ->setStoreId(1)
        ->setCustomerGroupId(0);
    $quote->addProduct($product, 1);
    $quote->getShippingAddress();
    $quote->collectTotals();

    $item = $quote->getAllVisibleItems()[0];

    expect((float) $item->getPrice())->toBe(20.0)
        ->and((float) $item->getBaseRowTotal())->toBe(20.0)
        ->and((float) $quote->getBaseSubtotal())->toBe(20.0);
});

it('gives a product with a customer group the catalog rule final price', function (): void {
    $product = Mage::getModel('catalog/product')->setStoreId(1)->load($this->productId);
    $product->setCustomerGroupId(0);

    expect((float) $product->getFinalPrice())->toBe(20.0);
});

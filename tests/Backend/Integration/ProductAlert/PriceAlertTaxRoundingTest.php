<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

$alertConfigPaths = [
    'catalog/productalert/allow_price',
    'tax/calculation/price_includes_tax',
    'tax/calculation/based_on',
    'tax/defaults/country',
    'tax/display/type',
];

beforeEach(function () use ($alertConfigPaths): void {
    $store = Mage::app()->getWebsite(1)->getDefaultStore();
    $this->store = $store;
    $this->originalConfig = [];
    foreach ($alertConfigPaths as $path) {
        $this->originalConfig[$path] = $store->getConfig($path);
    }
    $store->setConfig('catalog/productalert/allow_price', '1');
    $store->setConfig('tax/calculation/price_includes_tax', '0');
    $store->setConfig('tax/calculation/based_on', 'shipping');
    $store->setConfig('tax/defaults/country', 'IT');
    $store->setConfig('tax/display/type', (string) Mage_Tax_Model_Config::DISPLAY_TYPE_INCLUDING_TAX);

    $this->write = Mage::getSingleton('core/resource')->getConnection('core_write');
    $this->write->beginTransaction();
});

afterEach(function (): void {
    $this->write->rollBack();
    foreach ($this->originalConfig as $path => $value) {
        $this->store->setConfig($path, $value);
    }
});

it('puts in the price alert email the price with tax that the cart charges', function (): void {
    $resource = Mage::getSingleton('core/resource');

    $productId = (int) Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToFilter('type_id', 'simple')
        ->addAttributeToFilter('tax_class_id', ['gt' => 0])
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    $priceAttribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'price');
    $specialPriceAttribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'special_price');
    $decimalTable = $priceAttribute->getBackendTable();
    $this->write->delete($decimalTable, [
        'entity_id = ?' => $productId,
        'attribute_id IN (?)' => [(int) $priceAttribute->getId(), (int) $specialPriceAttribute->getId()],
    ]);
    $this->write->insert($decimalTable, [
        'entity_type_id' => (int) $priceAttribute->getEntityTypeId(),
        'attribute_id' => (int) $priceAttribute->getId(),
        'store_id' => 0,
        'entity_id' => $productId,
        'value' => 11.4754,
    ]);
    $this->write->delete($resource->getTableName('catalog/product_attribute_tier_price'), ['entity_id = ?' => $productId]);
    $this->write->delete($resource->getTableName('catalog/product_attribute_group_price'), ['entity_id = ?' => $productId]);
    $this->write->delete($resource->getTableName('catalogrule/rule_product_price'), ['product_id = ?' => $productId]);
    $productClassId = (int) Mage::getModel('catalog/product')->setStoreId(0)->load($productId)->getTaxClassId();

    $this->write->insert($resource->getTableName('tax/tax_calculation_rate'), [
        'tax_country_id' => 'IT',
        'tax_region_id' => 0,
        'tax_postcode' => '*',
        'code' => 'IT-ALERT-ROUNDING-' . uniqid(),
        'rate' => 22,
    ]);
    $rateId = (int) $this->write->lastInsertId();
    $this->write->insert($resource->getTableName('tax/tax_calculation_rule'), [
        'code' => 'IT-ALERT-ROUNDING-' . uniqid(),
        'priority' => 0,
        'position' => 0,
    ]);
    $ruleId = (int) $this->write->lastInsertId();
    $this->write->insert($resource->getTableName('tax/tax_calculation'), [
        'tax_calculation_rate_id' => $rateId,
        'tax_calculation_rule_id' => $ruleId,
        'customer_tax_class_id' => (int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID),
        'product_tax_class_id' => $productClassId,
    ]);

    expect($productId)->toBeGreaterThan(0);

    $customerId = (int) Mage::getResourceModel('customer/customer_collection')
        ->addAttributeToFilter('website_id', 1)
        ->setPageSize(1)
        ->getFirstItem()
        ->getId();
    $this->write->delete($resource->getTableName('productalert/price'), ['website_id = ?' => 1]);
    $this->write->insert($resource->getTableName('productalert/price'), [
        'customer_id' => $customerId,
        'product_id' => $productId,
        'price' => 20,
        'website_id' => 1,
        'add_date' => Mage::app()->getLocale()->formatDateForDb('now'),
        'status' => 0,
        'send_count' => 0,
    ]);

    $email = new class extends Mage_ProductAlert_Model_Email {
        /** @var array<int, float> */
        public array $finalPrices = [];

        #[\Override]
        public function addPriceProduct(Mage_Catalog_Model_Product $product)
        {
            $this->finalPrices[] = (float) $product->getFinalPrice();
            return $this;
        }

        #[\Override]
        public function send()
        {
            return false;
        }
    };

    Mage::unregister('_singleton/tax/calculation');
    Mage::unregister('_resource_singleton/tax/calculation');

    $observer = Mage::getModel('productalert/observer');
    $process = new ReflectionMethod($observer, '_processPrice');
    $process->invoke($observer, $email);

    expect($email->finalPrices)->toBe([14.01]);
});

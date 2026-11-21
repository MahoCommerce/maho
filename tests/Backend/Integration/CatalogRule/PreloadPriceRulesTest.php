<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function preloadRuleWrite(): Maho\Db\Adapter\AdapterInterface
{
    return Mage::getSingleton('core/resource')->getConnection('core_write');
}

function preloadRuleTable(string $name): string
{
    return Mage::getSingleton('core/resource')->getTableName($name);
}

it('reloads preloaded prices when the same quote changes customer group', function () {
    $product = Mage::getResourceModel('catalog/product_collection')
        ->addAttributeToSelect('price')
        ->addAttributeToFilter('type_id', 'simple')
        ->setPageSize(1)
        ->getFirstItem();
    $productId = (int) $product->getId();
    $storeId = (int) Mage::app()->getStore()->getId();
    $websiteId = (int) Mage::app()->getStore($storeId)->getWebsiteId();
    $date = Mage::app()->getLocale()->utcToStore($storeId)->format(Mage_Core_Model_Locale::DATE_FORMAT);
    $write = preloadRuleWrite();
    $table = preloadRuleTable('catalogrule/rule_product_price');

    $write->delete($table, [
        'rule_date = ?' => Mage::app()->getLocale()->formatDateForDb($date, withTime: false),
        'website_id = ?' => $websiteId,
        'product_id = ?' => $productId,
    ]);
    foreach ([0 => 10.0, 1 => 20.0] as $groupId => $price) {
        $write->insert($table, [
            'rule_date' => Mage::app()->getLocale()->formatDateForDb($date, withTime: false),
            'website_id' => $websiteId,
            'customer_group_id' => $groupId,
            'product_id' => $productId,
            'rule_price' => $price,
        ]);
    }

    $quote = new class ($storeId, $productId) {
        public int $customerGroupId = 0;

        public function __construct(
            private readonly int $storeId,
            private readonly int $productId,
        ) {
        }

        public function getStoreId(): int
        {
            return $this->storeId;
        }

        public function getStore(): Mage_Core_Model_Store
        {
            return Mage::app()->getStore($this->storeId);
        }

        public function getCustomerGroupId(): int
        {
            return $this->customerGroupId;
        }

        /** @return array<int, Maho\DataObject> */
        public function getAllItems(): array
        {
            return [new Maho\DataObject(['product_id' => $this->productId])];
        }
    };

    $event = new Maho\Event();
    $event->setQuote($quote);
    $observer = new Maho\Event\Observer();
    $observer->setEvent($event);
    $catalogRuleObserver = Mage::getSingleton('catalogrule/observer');
    $priceCache = new ReflectionProperty($catalogRuleObserver, '_rulePrices');

    $catalogRuleObserver->preloadPriceRules($observer);
    $first = $priceCache->getValue($catalogRuleObserver);

    $quote->customerGroupId = 1;
    $catalogRuleObserver->preloadPriceRules($observer);
    $second = $priceCache->getValue($catalogRuleObserver);

    $dateKey = Mage::app()->getLocale()->formatDateForDb($date, withTime: false);
    expect((float) $first["{$dateKey}|{$websiteId}|0|{$productId}"])->toBe(10.0)
        ->and((float) $second["{$dateKey}|{$websiteId}|1|{$productId}"])->toBe(20.0);

    $write->delete($table, [
        'rule_date = ?' => $dateKey,
        'website_id = ?' => $websiteId,
        'product_id = ?' => $productId,
    ]);
});

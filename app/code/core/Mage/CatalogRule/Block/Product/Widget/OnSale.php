<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

/**
 * Frontend widget listing products with an active catalog price rule discount,
 * optionally restricted to a single rule ("offer of the month").
 */
class Mage_CatalogRule_Block_Product_Widget_OnSale extends Mage_Catalog_Block_Product_Widget_Abstract
{
    public const DEFAULT_ORDER = 'newest';

    protected $_pageVarName = 'os';
    protected $_cacheKeyPrefix = 'CATALOGRULE_PRODUCT_WIDGET_ONSALE';

    /**
     * Optional catalog price rule id to restrict the list to. Empty = all active rules.
     */
    public function getRuleId(): ?int
    {
        $ruleId = $this->getData('rule_id');
        return $ruleId ? (int) $ruleId : null;
    }

    public function getOrderMode(): string
    {
        if (!$this->hasData('order')) {
            $this->setData('order', self::DEFAULT_ORDER);
        }
        return (string) $this->getData('order');
    }

    #[\Override]
    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), [
            (int) $this->getRuleId(),
            $this->getOrderMode(),
            (int) $this->onlyInStock(),
        ]);
    }

    /**
     * Build the product collection of items with an active rule price today.
     */
    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        $store = Mage::app()->getStore();
        $websiteId = $store->getWebsiteId();
        $customerGroupId = Mage::getSingleton('customer/session')->getCustomerGroupId();
        $date = Mage::app()->getLocale()->utcToStore($store)->format(Mage_Core_Model_Locale::DATE_FORMAT);

        /** @var Mage_CatalogRule_Model_Resource_Rule $resource */
        $resource = Mage::getResourceSingleton('catalogrule/rule');
        $rulePrices = $resource->getActiveRuleProductPrices($date, $websiteId, $customerGroupId);

        if ($this->getRuleId()) {
            $ruleProductIds = array_flip($resource->getRuleProductIds($this->getRuleId()));
            $rulePrices = array_intersect_key($rulePrices, $ruleProductIds);
        }

        $productIds = array_map('intval', array_keys($rulePrices));

        $collection = $this->_prepareStorefrontCollection($productIds);
        if (!empty($productIds)) {
            $this->_applyOrder($collection, $productIds, $rulePrices);
            $collection->setPageSize($this->getProductsCount())->setCurPage(1);
        }

        return $collection;
    }

    /**
     * Apply the configured ordering to the collection.
     *
     * @param int[] $productIds
     * @param array<int, float> $rulePrices product_id => rule_price
     */
    protected function _applyOrder(Mage_Catalog_Model_Resource_Product_Collection $collection, array $productIds, array $rulePrices): void
    {
        match ($this->getOrderMode()) {
            'random' => $collection->getSelect()->orderRand(),
            'bestselling' => $collection->getSelect()->order($this->_getOrderByIdsExpr($this->_sortByBestselling($productIds))),
            'biggest_discount' => $collection->getSelect()->order($this->_getOrderByIdsExpr($this->_sortByDiscount($productIds, $rulePrices))),
            default => $collection->addAttributeToSort('created_at', 'desc'),
        };
    }

    /**
     * Order product ids by total quantity sold (descending), appending unsold ids at the end.
     *
     * @param int[] $productIds
     * @return int[]
     */
    protected function _sortByBestselling(array $productIds): array
    {
        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        $select = $adapter->select()
            ->from(['oi' => $resource->getTableName('sales/order_item')], ['product_id'])
            ->joinInner(
                ['o' => $resource->getTableName('sales/order')],
                'o.entity_id = oi.order_id',
                [],
            )
            ->where('oi.product_id IN (?)', $productIds)
            ->where('o.state <> ?', Mage_Sales_Model_Order::STATE_CANCELED)
            ->where('oi.parent_item_id IS NULL')
            ->where('oi.store_id = ?', (int) Mage::app()->getStore()->getId())
            ->group('oi.product_id')
            ->order(new Maho\Db\Expr('SUM(oi.qty_ordered) DESC'));
        $sold = array_map('intval', $adapter->fetchCol($select));

        return array_merge($sold, array_values(array_diff($productIds, $sold)));
    }

    /**
     * Order product ids by absolute discount amount (base price - rule price), descending.
     *
     * @param int[] $productIds
     * @param array<int, float> $rulePrices
     * @return int[]
     */
    protected function _sortByDiscount(array $productIds, array $rulePrices): array
    {
        $basePrices = [];
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->addAttributeToSelect('price')->addIdFilter($productIds);
        foreach ($collection as $product) {
            $basePrices[(int) $product->getId()] = (float) $product->getPrice();
        }

        $discounts = [];
        foreach ($productIds as $id) {
            $base = $basePrices[$id] ?? 0.0;
            $rule = (float) ($rulePrices[$id] ?? $base);
            $discounts[$id] = $base - $rule;
        }
        arsort($discounts);

        return array_map('intval', array_keys($discounts));
    }
}

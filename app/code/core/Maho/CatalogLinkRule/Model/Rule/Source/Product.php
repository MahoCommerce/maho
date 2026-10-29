<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_Rule_Source_Product extends Mage_CatalogRule_Model_Rule_Condition_Product
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_source_product');
    }

    /**
     * Add status, visibility and stock status to special attributes
     */
    #[\Override]
    protected function _addSpecialAttributes(array &$attributes): void
    {
        parent::_addSpecialAttributes($attributes);
        $attributes['status'] = Mage::helper('cataloglinkrule')->__('Status');
        $attributes['visibility'] = Mage::helper('cataloglinkrule')->__('Visibility');
        $attributes['stock_status'] = Mage::helper('cataloglinkrule')->__('Stock Status');
    }

    #[\Override]
    protected function _prepareValueOptions()
    {
        if ($this->getAttribute() === 'stock_status' && !$this->getData('value_select_options')) {
            $options = Mage::getSingleton('cataloginventory/source_stock')->toOptionArray();
            $this->setData('value_select_options', $options);
            $this->setData('value_option', array_column($options, 'label', 'value'));
        }

        return parent::_prepareValueOptions();
    }

    #[\Override]
    public function getInputType(): string
    {
        return $this->getAttribute() === 'stock_status' ? 'select' : parent::getInputType();
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return $this->getAttribute() === 'stock_status' ? 'select' : parent::getValueElementType();
    }

    /**
     * Read the stock status index, like the storefront: a configurable product is out of stock when
     * all its children are. A product is in stock when it is in stock in one of its websites.
     */
    #[\Override]
    public function collectValidatedAttributes($productCollection)
    {
        if ($this->getAttribute() !== 'stock_status') {
            return parent::collectValidatedAttributes($productCollection);
        }

        if (!$productCollection->hasFlag('cataloglinkrule_stock_status')) {
            $inStock = $productCollection->getConnection()->select()
                ->from(['ss' => $productCollection->getTable('cataloginventory/stock_status')], [new Maho\Db\Expr('1')])
                ->where('ss.product_id = e.entity_id')
                ->where('ss.stock_id = ?', Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID)
                ->where('ss.stock_status = ?', Mage_CatalogInventory_Model_Stock_Status::STATUS_IN_STOCK);
            $productCollection->getSelect()->columns([
                'stock_status' => new Maho\Db\Expr('CASE WHEN EXISTS (' . $inStock . ') THEN 1 ELSE 0 END'),
            ]);
            $productCollection->setFlag('cataloglinkrule_stock_status', true);
        }

        return $this;
    }
}

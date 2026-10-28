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
     * Add status, visibility and stock availability to special attributes
     */
    #[\Override]
    protected function _addSpecialAttributes(array &$attributes): void
    {
        parent::_addSpecialAttributes($attributes);
        $attributes['status'] = Mage::helper('cataloglinkrule')->__('Status');
        $attributes['visibility'] = Mage::helper('cataloglinkrule')->__('Visibility');
        $attributes['is_in_stock'] = Mage::helper('cataloglinkrule')->__('Stock Availability');
    }

    #[\Override]
    protected function _prepareValueOptions()
    {
        if ($this->getAttribute() === 'is_in_stock' && !$this->getData('value_select_options')) {
            $options = Mage::getSingleton('cataloginventory/source_stock')->toOptionArray();
            $this->setData('value_select_options', $options);
            $this->setData('value_option', array_column($options, 'label', 'value'));
        }

        return parent::_prepareValueOptions();
    }

    #[\Override]
    public function getInputType(): string
    {
        return $this->getAttribute() === 'is_in_stock' ? 'select' : parent::getInputType();
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return $this->getAttribute() === 'is_in_stock' ? 'select' : parent::getValueElementType();
    }

    /**
     * The stock is not an EAV attribute, so join the stock item of each product once
     */
    #[\Override]
    public function collectValidatedAttributes($productCollection)
    {
        if ($this->getAttribute() !== 'is_in_stock') {
            return parent::collectValidatedAttributes($productCollection);
        }

        if (!$productCollection->hasFlag('cataloglinkrule_is_in_stock')) {
            $productCollection->joinField(
                'is_in_stock',
                'cataloginventory/stock_item',
                'is_in_stock',
                'product_id=entity_id',
                '{{table}}.stock_id=' . Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID,
                'left',
            );
            $productCollection->setFlag('cataloglinkrule_is_in_stock', true);
        }

        return $this;
    }
}

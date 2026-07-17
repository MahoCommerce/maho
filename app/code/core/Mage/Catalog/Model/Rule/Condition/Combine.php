<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Rule_Condition_Combine extends Mage_Rule_Model_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('catalog/rule_condition_combine');
    }

    /**
     * Model factory prefix for the combine/product conditions this combine offers.
     * Subclasses re-skin the feature (e.g. catalogrule) by overriding this.
     */
    protected function _getConditionPrefix(): string
    {
        return 'catalog/rule_condition';
    }

    /**
     * Helper alias used to translate the condition select labels.
     */
    protected function _getConditionHelper(): string
    {
        return 'catalog';
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $prefix = $this->_getConditionPrefix();
        $helper = $this->_getConditionHelper();

        $productCondition = Mage::getModel('catalog/rule_condition_product');
        $productAttributes = $productCondition->loadAttributeOptions()->getAttributeOption();
        $attributes = [];
        foreach ($productAttributes as $code => $label) {
            $attributes[] = ['value' => $prefix . '_product|' . $code, 'label' => $label];
        }
        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            ['value' => $prefix . '_combine', 'label' => Mage::helper($helper)->__('Conditions Combination')],
            ['label' => Mage::helper($helper)->__('Product Attribute'), 'value' => $attributes],
        ]);
        return $conditions;
    }

    public function collectValidatedAttributes(Mage_Catalog_Model_Resource_Product_Collection $productCollection): self
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }
        return $this;
    }
}

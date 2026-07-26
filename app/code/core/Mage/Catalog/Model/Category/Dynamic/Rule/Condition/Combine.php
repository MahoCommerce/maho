<?php

/**
 * Condition combine for dynamic category rules, adding the stock conditions.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Dynamic_Rule_Condition_Combine extends Mage_Catalog_Model_Rule_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('catalog/category_dynamic_rule_condition_combine');
    }

    #[\Override]
    protected function _getConditionPrefix(): string
    {
        return 'catalog/category_dynamic_rule_condition';
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $conditions = parent::getNewChildSelectOptions();

        $stockAttributes = [];
        $stockCondition = Mage::getModel('catalog/category_dynamic_rule_condition_stock');
        foreach ($stockCondition->loadAttributeOptions()->getAttributeOption() as $code => $label) {
            $stockAttributes[] = [
                'value' => 'catalog/category_dynamic_rule_condition_stock|' . $code,
                'label' => $label,
            ];
        }

        $conditions[] = [
            'label' => Mage::helper($this->_getConditionHelper())->__('Stock'),
            'value' => $stockAttributes,
        ];

        return $conditions;
    }
}

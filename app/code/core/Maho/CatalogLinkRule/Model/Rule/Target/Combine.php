<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_Rule_Target_Combine extends Mage_Rule_Model_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_target_combine');
    }

    #[\Override]
    public function asHtml()
    {
        $html = $this->getTypeElement()->getHtml() .
                Mage::helper('cataloglinkrule')->__('Target products matching %s of these conditions are %s:', $this->getAggregatorElement()->getHtml(), $this->getValueElement()->getHtml());
        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }
        return $html;
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $productCondition = Mage::getModel('cataloglinkrule/rule_target_product');
        $productAttributes = $productCondition->loadAttributeOptions()->getAttributeOption();

        $attributes = [];
        foreach ($productAttributes as $code => $label) {
            $attributes[] = [
                'value' => 'cataloglinkrule/rule_target_product|' . $code,
                'label' => $label,
            ];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            [
                'value' => 'cataloglinkrule/rule_target_combine',
                'label' => Mage::helper('cataloglinkrule')->__('Conditions Combination'),
            ],
            [
                'label' => Mage::helper('cataloglinkrule')->__('Product Attribute'),
                'value' => $attributes,
            ],
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

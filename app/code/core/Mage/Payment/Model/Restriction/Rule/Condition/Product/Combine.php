<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Restriction_Rule_Condition_Product_Combine extends Mage_Rule_Model_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_product_combine');
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $productCondition = Mage::getModel('payment/restriction_rule_condition_product');
        $productAttributes = $productCondition->loadAttributeOptions()->getAttributeOption();
        $attributes = [];
        foreach ($productAttributes as $code => $label) {
            $attributes[] = ['value' => 'payment/restriction_rule_condition_product|' . $code, 'label' => $label];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            [
                'value' => 'payment/restriction_rule_condition_product_combine',
                'label' => Mage::helper('payment')->__('Conditions Combination'),
            ],
            [
                'label' => Mage::helper('payment')->__('Product Attribute'),
                'value' => $attributes,
            ],
        ]);
        return $conditions;
    }

    /**
     * Collect validated attributes
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes($productCollection)
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }
        return $this;
    }
}

<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_Rule_Target_Combine extends Mage_Rule_Model_Condition_Combine
{
    /**
     * Initialize the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_target_combine');
    }

    /**
     * Get new child select options
     *
     * @return array
     */
    #[\Override]
    public function getNewChildSelectOptions()
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

        // Add source matching conditions
        $sourceMatchCondition = Mage::getModel('cataloglinkrule/rule_target_sourceMatch');
        $sourceMatchAttributes = $sourceMatchCondition->loadAttributeOptions()->getAttributeOption();

        $sourceMatchOptions = [];
        foreach ($sourceMatchAttributes as $code => $label) {
            $sourceMatchOptions[] = [
                'value' => 'cataloglinkrule/rule_target_sourceMatch|' . $code,
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
            [
                'label' => Mage::helper('cataloglinkrule')->__('Match Source Product'),
                'value' => $sourceMatchOptions,
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

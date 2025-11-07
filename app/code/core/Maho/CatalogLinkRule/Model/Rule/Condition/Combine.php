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

class Maho_CatalogLinkRule_Model_Rule_Condition_Combine extends Mage_Rule_Model_Condition_Combine
{
    /**
     * Initialize the model
     */
    public function __construct()
    {
        parent::__construct();
        $this->setType('cataloglinkrule/rule_condition_combine');
    }

    /**
     * Get new child select options
     */
    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $productCondition = Mage::getModel('cataloglinkrule/rule_condition_product');
        $productAttributes = $productCondition->loadAttributeOptions()->getAttributeOption();

        $attributes = [];
        foreach ($productAttributes as $code => $label) {
            $attributes[] = [
                'value' => 'cataloglinkrule/rule_condition_product|' . $code,
                'label' => $label,
            ];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            [
                'value' => 'cataloglinkrule/rule_condition_combine',
                'label' => Mage::helper('cataloglinkrule')->__('Conditions Combination'),
            ],
            [
                'label' => Mage::helper('cataloglinkrule')->__('Product Attribute'),
                'value' => $attributes,
            ],
        ]);

        return $conditions;
    }

    /**
     * Collect validated attributes
     *
     * @return $this
     */
    public function collectValidatedAttributes(Mage_Catalog_Model_Resource_Product_Collection $productCollection): self
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }

        return $this;
    }
}

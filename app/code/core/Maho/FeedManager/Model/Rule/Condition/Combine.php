<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Model_Rule_Condition_Combine extends Mage_Rule_Model_Condition_Combine
{
    /**
     * Local form instance (since DynamicRule doesn't extend Mage_Rule_Model_Abstract)
     */
    protected ?\Maho\Data\Form $_form = null;

    public function __construct()
    {
        parent::__construct();
        $this->setType('feedmanager/rule_condition_combine');
    }

    /**
     * Override getForm to use local form instance
     * (DynamicRule no longer extends Mage_Rule_Model_Abstract)
     */
    #[\Override]
    public function getForm(): \Maho\Data\Form
    {
        if ($this->_form === null) {
            $this->_form = new \Maho\Data\Form();
        }
        return $this->_form;
    }

    /**
     * Set the form instance
     */
    public function setForm(\Maho\Data\Form $form): self
    {
        $this->_form = $form;
        return $this;
    }

    /**
     * Special attributes that are not EAV attributes
     */
    protected array $_specialAttributes = [
        'qty',
        'is_in_stock',
        'type_id',
        'category_ids',
        'attribute_set_id',
    ];

    /**
     * Get available conditions for adding new child
     */
    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $productCondition = Mage::getModel('feedmanager/rule_condition_product');
        $productAttributes = $productCondition->loadAttributeOptions()->getAttributeOption();

        $specialAttributes = [];
        $productAttrs = [];

        foreach ($productAttributes as $code => $label) {
            $option = [
                'value' => 'feedmanager/rule_condition_product|' . $code,
                'label' => $label,
            ];

            if (in_array($code, $this->_specialAttributes)) {
                $specialAttributes[] = $option;
            } else {
                $productAttrs[] = $option;
            }
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            [
                'value' => 'feedmanager/rule_condition_combine',
                'label' => Mage::helper('feedmanager')->__('Conditions Combination'),
            ],
            [
                'label' => Mage::helper('feedmanager')->__('Special Attributes'),
                'value' => $specialAttributes,
            ],
            [
                'label' => Mage::helper('feedmanager')->__('Product Attribute'),
                'value' => $productAttrs,
            ],
        ]);

        return $conditions;
    }

    /**
     * Collect validated attributes for product collection
     *
     * @param Mage_Catalog_Model_Resource_Product_Collection $productCollection
     * @return $this
     */
    public function collectValidatedAttributes($productCollection): self
    {
        foreach ($this->getConditions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }
        return $this;
    }
}

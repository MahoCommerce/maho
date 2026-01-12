<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Restriction_Rule_Condition_Combine extends Mage_Rule_Model_Condition_Combine
{
    /**
     * Fallback form instance
     */
    protected ?\Maho\Data\Form $_form = null;

    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_combine');
        $this->loadAggregatorOptions();
        $this->loadValueOptions();
    }

    #[\Override]
    public function loadValueOptions()
    {
        $this->setValueOption([
            1 => Mage::helper('payment')->__('TRUE'),
            0 => Mage::helper('payment')->__('FALSE'),
        ]);
        return $this;
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        $addressCondition = Mage::getModel('payment/restriction_rule_condition_address');
        $addressAttributes = $addressCondition->loadAttributeOptions()->getAttributeOption();
        $attributes = [];
        foreach ($addressAttributes as $code => $label) {
            $attributes[] = ['value' => 'payment/restriction_rule_condition_address|' . $code, 'label' => $label];
        }

        $customerCondition = Mage::getModel('payment/restriction_rule_condition_customer');
        $customerAttributes = $customerCondition->loadAttributeOptions()->getAttributeOption();
        $customerAttributeOptions = [];
        foreach ($customerAttributes as $code => $label) {
            $customerAttributeOptions[] = ['value' => 'payment/restriction_rule_condition_customer|' . $code, 'label' => $label];
        }

        $conditions = parent::getNewChildSelectOptions();
        $conditions = array_merge_recursive($conditions, [
            [
                'value' => 'payment/restriction_rule_condition_combine',
                'label' => Mage::helper('payment')->__('Conditions Combination'),
            ],
            [
                'label' => Mage::helper('payment')->__('Cart Attribute'),
                'value' => $attributes,
            ],
            [
                'label' => Mage::helper('payment')->__('Customer Attribute'),
                'value' => $customerAttributeOptions,
            ],
            [
                'label' => Mage::helper('payment')->__('Product Attribute'),
                'value' => [
                    [
                        'value' => 'payment/restriction_rule_condition_product_found',
                        'label' => Mage::helper('payment')->__('Product attribute combination'),
                    ],
                    [
                        'value' => 'payment/restriction_rule_condition_product_subselect',
                        'label' => Mage::helper('payment')->__('Products subselection'),
                    ],
                ],
            ],
        ]);

        $additional = new \Maho\DataObject();
        Mage::dispatchEvent('payment_restriction_rule_condition_combine', ['additional' => $additional]);
        if ($additionalConditions = $additional->getConditions()) {
            $conditions = array_merge_recursive($conditions, $additionalConditions);
        }

        return $conditions;
    }

    /**
     * Override to ensure rule is properly set on conditions
     */
    #[\Override]
    public function loadArray($arr, $key = 'conditions')
    {
        parent::loadArray($arr, $key);

        // Ensure all child conditions have the rule set
        foreach ($this->getConditions() as $condition) {
            $condition->setRule($this->getRule());
        }

        return $this;
    }

    /**
     * Override getForm to provide fallback when rule is not available
     */
    #[\Override]
    public function getForm()
    {
        if ($this->getRule()) {
            return $this->getRule()->getForm();
        }

        // Fallback: create a basic form if rule is not available
        if (!$this->_form) {
            $this->_form = new \Maho\Data\Form();
        }
        return $this->_form;
    }

    /**
     * Override asHtml to ensure proper DOM structure
     */
    #[\Override]
    public function asHtml()
    {
        // Ensure we have the basic structure even when empty
        if (!$this->getConditions()) {
            $this->setData('conditions', []);
        }

        return parent::asHtml();
    }
}

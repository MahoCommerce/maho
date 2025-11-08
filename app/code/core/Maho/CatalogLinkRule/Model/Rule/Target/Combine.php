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

class Maho_CatalogLinkRule_Model_Rule_Target_Combine extends Mage_Rule_Model_Action_Collection
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
     */
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
     * Override to use __ separators instead of : separators
     * This makes the HTML compatible with rules.js which expects __ separators
     */
    #[\Override]
    public function getNewChildElement()
    {
        $prefix = $this->getPrefix() ?: 'actions';
        $element = $this->getForm()->addField($prefix . '__' . $this->getId() . '__new_child', 'select', [
            'name' => 'rule[' . $this->getPrefix() . '][' . $this->getId() . '][new_child]',
            'values' => $this->getNewChildSelectOptions(),
            'value_name' => $this->getNewChildName(),
        ]);

        $renderer = Mage::getBlockSingleton('rule/newchild');
        if ($renderer instanceof Varien_Data_Form_Element_Renderer_Interface) {
            $element->setRenderer($renderer);
        }

        return $element;
    }

    /**
     * Override to use __ separators instead of : separators
     * This makes the HTML compatible with rules.js which expects __ separators
     */
    #[\Override]
    public function asHtmlRecursive()
    {
        $prefix = $this->getPrefix() ?: 'actions';
        $htmlId = $prefix . '__' . $this->getId() . '__children';
        $html = $this->asHtml() . '<ul id="' . $htmlId . '">';
        foreach ($this->getActions() as $cond) {
            $html .= '<li>' . $cond->asHtmlRecursive() . '</li>';
        }
        $html .= '<li>' . $this->getNewChildElement()->getHtml() . '</li></ul>';
        return $html;
    }

    /**
     * Collect validated attributes
     *
     * @return $this
     */
    public function collectValidatedAttributes(Mage_Catalog_Model_Resource_Product_Collection $productCollection): self
    {
        foreach ($this->getActions() as $condition) {
            $condition->collectValidatedAttributes($productCollection);
        }

        return $this;
    }

    /**
     * Validate product against target conditions
     */
    public function validate(Maho\DataObject $object): bool
    {
        foreach ($this->getActions() as $action) {
            if (!$action->validate($object)) {
                return false;
            }
        }
        return true;
    }
}

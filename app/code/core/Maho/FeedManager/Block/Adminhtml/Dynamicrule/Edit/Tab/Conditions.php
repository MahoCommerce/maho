<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Dynamic Rule Conditions Tab
 *
 * Uses standard Maho rules engine for conditions, with output configuration below.
 */
class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit_Tab_Conditions extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $rule = $this->_getRule();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('rule_');

        // Conditions fieldset with standard rules UX
        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml', ['form' => 'rule_conditions_fieldset']));

        $fieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => $this->__('Conditions (leave blank to always match)'),
        ])->setRenderer($renderer);

        $fieldset->addField('conditions', 'text', [
            'name' => 'conditions',
            'label' => $this->__('Conditions'),
            'title' => $this->__('Conditions'),
        ])->setRule($rule)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        // Output fieldset
        $outputFieldset = $form->addFieldset('output_fieldset', [
            'legend' => $this->__('Output Configuration'),
        ]);

        $outputFieldset->addField('output_type', 'select', [
            'name' => 'output_type',
            'label' => $this->__('Output Type'),
            'title' => $this->__('Output Type'),
            'required' => true,
            'values' => Maho_FeedManager_Model_DynamicRule::getOutputTypeOptions(),
            'note' => $this->__('What type of value to output when conditions match'),
        ]);

        $outputFieldset->addField('output_value', 'text', [
            'name' => 'output_value',
            'label' => $this->__('Static Value / Prefix'),
            'title' => $this->__('Static Value / Prefix'),
            'note' => $this->__('For "Static Value": the value to output. For "Combined": the prefix before the attribute value.'),
        ]);

        $outputFieldset->addField('output_attribute', 'select', [
            'name' => 'output_attribute',
            'label' => $this->__('Product Attribute'),
            'title' => $this->__('Product Attribute'),
            'values' => $this->_getAttributeOptions(),
            'note' => $this->__('The product attribute to use for output value'),
        ]);

        $form->setValues($rule->getData());
        $this->setForm($form);

        // Add JavaScript to show/hide fields based on output type
        $this->setChild('form_after', $this->getLayout()->createBlock('core/template')
            ->setTemplate('maho/feedmanager/dynamicrule/output-js.phtml'));

        return parent::_prepareForm();
    }

    /**
     * Get product attribute options for output
     */
    protected function _getAttributeOptions(): array
    {
        $options = ['' => $this->__('-- Select Attribute --')];

        foreach (Maho_FeedManager_Model_DynamicRule::getOutputAttributeOptions() as $attr) {
            $options[$attr['value']] = $attr['label'];
        }

        return $options;
    }

    protected function _getRule(): Maho_FeedManager_Model_DynamicRule
    {
        return Mage::registry('current_dynamic_rule') ?: Mage::getModel('feedmanager/dynamicRule');
    }
}

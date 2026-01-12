<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Sequence_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $sequence = Mage::registry('current_email_sequence');
        $segment = Mage::registry('current_customer_segment');

        $form = new \Maho\Data\Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/saveSequence', ['id' => $sequence->getId()]),
            'method' => 'post',
        ]);

        $form->setUseContainer(true);
        $this->setForm($form);

        // General Settings Fieldset
        $fieldset = $form->addFieldset('general', [
            'legend' => Mage::helper('customersegmentation')->__('Sequence Step Settings'),
        ]);

        // Hidden field for sequence ID (when editing existing)
        if ($sequence->getId()) {
            $fieldset->addField('sequence_id', 'hidden', [
                'name' => 'sequence_id',
                'value' => $sequence->getId(),
            ]);
        }

        $fieldset->addField('segment_id', 'hidden', [
            'name' => 'segment_id',
            'value' => $segment->getId(),
        ]);

        $fieldset->addField('trigger_event', 'hidden', [
            'name' => 'trigger_event',
            'value' => $sequence->getTriggerEvent(),
        ]);

        $fieldset->addField('step_number', 'text', [
            'name' => 'step_number',
            'label' => Mage::helper('customersegmentation')->__('Step Number'),
            'title' => Mage::helper('customersegmentation')->__('Step Number'),
            'required' => true,
            'class' => 'validate-greater-than-zero validate-number',
            'note' => Mage::helper('customersegmentation')->__('Order in the email sequence (1, 2, 3...)'),
        ]);

        $fieldset->addField('template_id', 'select', [
            'name' => 'template_id',
            'label' => Mage::helper('customersegmentation')->__('Email Template'),
            'title' => Mage::helper('customersegmentation')->__('Newsletter Template'),
            'required' => true,
            'values' => $this->getTemplateOptions(),
            'note' => Mage::helper('customersegmentation')->__('Newsletter template to use for this step'),
        ]);

        $fieldset->addField('delay_minutes', 'text', [
            'name' => 'delay_minutes',
            'label' => Mage::helper('customersegmentation')->__('Delay (Minutes)'),
            'title' => Mage::helper('customersegmentation')->__('Delay before sending'),
            'required' => true,
            'class' => 'validate-zero-or-greater validate-number',
            'note' => Mage::helper('customersegmentation')->__('Minutes to wait before sending (0 = immediate, 60 = 1 hour, 1440 = 1 day)'),
        ]);

        $fieldset->addField('is_active', 'select', [
            'name' => 'is_active',
            'label' => Mage::helper('customersegmentation')->__('Active'),
            'title' => Mage::helper('customersegmentation')->__('Is Active'),
            'values' => Mage::getSingleton('adminhtml/system_config_source_yesno')->toOptionArray(),
            'note' => Mage::helper('customersegmentation')->__('Enable/disable this sequence step'),
        ]);

        // Coupon Generation Fieldset
        $couponFieldset = $form->addFieldset('coupon_settings', [
            'legend' => Mage::helper('customersegmentation')->__('Coupon Generation Settings'),
        ]);

        $couponFieldset->addField('generate_coupon', 'select', [
            'name' => 'generate_coupon',
            'label' => Mage::helper('customersegmentation')->__('Generate Coupon'),
            'title' => Mage::helper('customersegmentation')->__('Generate unique coupon'),
            'values' => Mage::getSingleton('adminhtml/system_config_source_yesno')->toOptionArray(),
            'note' => Mage::helper('customersegmentation')->__('Generate a unique coupon code for each customer'),
            'onchange' => 'toggleCouponFields(this.value);',
        ]);

        $couponFieldset->addField('coupon_sales_rule_id', 'select', [
            'name' => 'coupon_sales_rule_id',
            'label' => Mage::helper('customersegmentation')->__('Base Sales Rule'),
            'title' => Mage::helper('customersegmentation')->__('Sales rule to base coupon on'),
            'values' => $this->getSalesRuleOptions(),
            'note' => Mage::helper('customersegmentation')->__('Sales rule to use as template for generated coupons'),
        ]);

        $couponFieldset->addField('coupon_prefix', 'text', [
            'name' => 'coupon_prefix',
            'label' => Mage::helper('customersegmentation')->__('Coupon Prefix'),
            'title' => Mage::helper('customersegmentation')->__('Prefix for coupon codes'),
            'note' => Mage::helper('customersegmentation')->__('Prefix for generated coupon codes (e.g., CART, WELCOME)'),
            'class' => 'validate-alphanum validate-length maximum-length-10',
        ]);

        $couponFieldset->addField('coupon_expires_days', 'text', [
            'name' => 'coupon_expires_days',
            'label' => Mage::helper('customersegmentation')->__('Expires After (Days)'),
            'title' => Mage::helper('customersegmentation')->__('Coupon expiration days'),
            'class' => 'validate-greater-than-zero validate-number',
            'note' => Mage::helper('customersegmentation')->__('Number of days until coupon expires (default: 30)'),
        ]);

        // Set form values
        $form->setValues($sequence->getData());

        // Add JavaScript for conditional fields
        $this->setChild('form_after', $this->getLayout()->createBlock('adminhtml/template')
            ->setTemplate('customersegmentation/segment/sequence/form_scripts.phtml'));

        return parent::_prepareForm();
    }

    /**
     * Get newsletter template options
     */
    protected function getTemplateOptions(): array
    {
        $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('-- Please Select --')]];

        $collection = Mage::getResourceModel('newsletter/template_collection');
        foreach ($collection as $template) {
            $options[] = [
                'value' => $template->getId(),
                'label' => $template->getTemplateCode() . ' (' . $template->getTemplateSubject() . ')',
            ];
        }

        return $options;
    }

    /**
     * Get sales rule options for coupon generation
     */
    protected function getSalesRuleOptions(): array
    {
        $helper = Mage::helper('customersegmentation/coupon');
        return $helper->getAvailableSalesRules();
    }
}

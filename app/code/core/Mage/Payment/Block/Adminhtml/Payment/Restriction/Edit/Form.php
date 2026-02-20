<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Block_Adminhtml_Payment_Restriction_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $form = new \Maho\Data\Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
        ]);

        $form->setHtmlIdPrefix('rule_');
        $form->setUseContainer(true);
        $this->setForm($form);

        // General Information fieldset
        $fieldset = $form->addFieldset('general', [
            'legend' => Mage::helper('payment')->__('General Information'),
        ]);

        if (Mage::registry('payment_restriction')->getId()) {
            $fieldset->addField('restriction_id', 'hidden', [
                'name' => 'restriction_id',
            ]);
        }

        $fieldset->addField('name', 'text', [
            'name'     => 'name',
            'label'    => Mage::helper('payment')->__('Name'),
            'required' => true,
        ]);

        $fieldset->addField('description', 'textarea', [
            'name'  => 'description',
            'label' => Mage::helper('payment')->__('Description'),
            'rows'  => 3,
        ]);

        $fieldset->addField('type', 'hidden', [
            'name'  => 'type',
            'value' => 'denylist',
        ]);

        $fieldset->addField('status', 'select', [
            'label'    => Mage::helper('payment')->__('Status'),
            'name'     => 'status',
            'required' => true,
            'options'  => [
                '1' => Mage::helper('payment')->__('Enabled'),
                '0' => Mage::helper('payment')->__('Disabled'),
            ],
        ]);


        // Payment Methods fieldset
        $fieldset = $form->addFieldset('restriction_payment_methods', [
            'legend' => Mage::helper('payment')->__('Payment Methods'),
        ]);

        $paymentMethods = $this->_getPaymentMethods();
        $fieldset->addField('restriction_payment_methods_field', 'multiselect', [
            'name'     => 'payment_methods[]',
            'label'    => Mage::helper('payment')->__('Payment Methods'),
            'values'   => $paymentMethods,
            'required' => true,
        ]);

        // Scope and Dates fieldset
        $fieldset = $form->addFieldset('restriction_scope', [
            'legend' => Mage::helper('payment')->__('Scope and Dates'),
        ]);

        $websites = $this->_getWebsites();
        $fieldset->addField('restriction_websites_field', 'multiselect', [
            'name'   => 'websites[]',
            'label'  => Mage::helper('payment')->__('Websites'),
            'values' => $websites,
            'note'   => Mage::helper('payment')->__('Leave empty to apply to all websites'),
        ]);

        $customerGroups = $this->_getCustomerGroups();
        $fieldset->addField('restriction_customer_groups_field', 'multiselect', [
            'name'   => 'customer_groups[]',
            'label'  => Mage::helper('payment')->__('Customer Groups'),
            'values' => $customerGroups,
            'note'   => Mage::helper('payment')->__('Leave empty to apply to all customer groups'),
        ]);

        $fieldset->addField('from_date', 'date', [
            'name'   => 'from_date',
            'label'  => Mage::helper('payment')->__('From Date'),
            'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
        ]);

        $fieldset->addField('to_date', 'date', [
            'name'   => 'to_date',
            'label'  => Mage::helper('payment')->__('To Date'),
            'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
        ]);

        // Conditions fieldset - Advanced rule widget
        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml/form/rule_conditions_fieldset'));

        $fieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => Mage::helper('payment')->__('Apply the restriction only if the following conditions are met (leave blank for all)'),
        ])->setRenderer($renderer);

        $rule = $this->_getRestrictionRule();
        $rule->setForm($form);

        $fieldset->addField('conditions', 'hidden', [
            'name' => 'conditions',
            'required' => false,
        ])->setRule($rule)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        if ($data = Mage::registry('payment_restriction')->getData()) {
            // Convert comma-separated values back to arrays for multiselect fields
            if (isset($data['payment_methods']) && is_string($data['payment_methods']) && !empty($data['payment_methods'])) {
                $data['restriction_payment_methods_field'] = explode(',', $data['payment_methods']);
            }
            if (isset($data['websites']) && is_string($data['websites']) && !empty($data['websites'])) {
                $data['restriction_websites_field'] = explode(',', $data['websites']);
            }
            if (isset($data['customer_groups']) && is_string($data['customer_groups']) && !empty($data['customer_groups'])) {
                $data['restriction_customer_groups_field'] = explode(',', $data['customer_groups']);
            }

            // Set form values for non-rule fields only
            $formData = $data;
            unset($formData['conditions_serialized']); // Don't set this as form data
            $form->setValues($formData);
        }

        return parent::_prepareForm();
    }

    protected function _getPaymentMethods(): array
    {
        $methods = [];
        $paymentMethods = Mage::helper('payment')->getPaymentMethods();

        foreach ($paymentMethods as $code => $data) {
            $title = $data['title'] ?? $code;
            $methods[] = ['value' => $code, 'label' => $title . ' (' . $code . ')'];
        }

        return $methods;
    }

    protected function _getRestrictionRule(): Mage_Payment_Model_Restriction_Rule
    {
        $restriction = Mage::registry('payment_restriction');
        $rule = Mage::getModel('payment/restriction_rule');

        if ($restriction && $restriction->getId()) {
            $rule->setData($restriction->getData());
        }

        // Always ensure conditions are properly initialized
        $rule->getConditions();

        return $rule;
    }

    protected function _getCustomerGroups(): array
    {
        $groups = [];
        $collection = Mage::getModel('customer/group')->getCollection();

        foreach ($collection as $group) {
            $groups[] = ['value' => $group->getId(), 'label' => $group->getCustomerGroupCode()];
        }

        return $groups;
    }

    protected function _getWebsites(): array
    {
        $websites = [];
        foreach (Mage::app()->getWebsites() as $website) {
            $websites[] = [
                'value' => $website->getId(),
                'label' => $website->getName() . ' (' . $website->getCode() . ')',
            ];
        }
        return $websites;
    }
}

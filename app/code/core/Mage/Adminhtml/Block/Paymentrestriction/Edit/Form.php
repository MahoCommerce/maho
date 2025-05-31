<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction edit form
 */
class Mage_Adminhtml_Block_Paymentrestriction_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm(): self
    {
        $form = new Varien_Data_Form([
            'id'     => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
        ]);

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
            'title'    => Mage::helper('payment')->__('Name'),
            'required' => true,
        ]);

        $fieldset->addField('description', 'textarea', [
            'name'  => 'description',
            'label' => Mage::helper('payment')->__('Description'),
            'title' => Mage::helper('payment')->__('Description'),
            'rows'  => 3,
        ]);

        $fieldset->addField('type', 'hidden', [
            'name'  => 'type',
            'value' => 'denylist',
        ]);

        $fieldset->addField('status', 'select', [
            'label'    => Mage::helper('payment')->__('Status'),
            'title'    => Mage::helper('payment')->__('Status'),
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
            'name'   => 'payment_methods[]',
            'label'  => Mage::helper('payment')->__('Payment Methods'),
            'title'  => Mage::helper('payment')->__('Payment Methods'),
            'values' => $paymentMethods,
            'note'   => Mage::helper('payment')->__('Leave empty to apply to all payment methods'),
        ]);

        // Conditions fieldset
        $fieldset = $form->addFieldset('conditions', [
            'legend' => Mage::helper('payment')->__('Conditions'),
        ]);

        $fieldset->addField('restriction_customer_groups', 'multiselect', [
            'name'   => 'customer_groups[]',
            'label'  => Mage::helper('payment')->__('Customer Groups'),
            'title'  => Mage::helper('payment')->__('Customer Groups'),
            'values' => $this->_getCustomerGroups(),
        ]);

        $fieldset->addField('restriction_countries', 'multiselect', [
            'name'   => 'countries[]',
            'label'  => Mage::helper('payment')->__('Countries'),
            'title'  => Mage::helper('payment')->__('Countries'),
            'values' => $this->_getCountries(),
        ]);

        $fieldset->addField('restriction_store_ids', 'multiselect', [
            'name'   => 'store_ids[]',
            'label'  => Mage::helper('payment')->__('Stores'),
            'title'  => Mage::helper('payment')->__('Stores'),
            'values' => $this->_getStores(),
        ]);

        $fieldset->addField('min_order_total', 'text', [
            'name'  => 'min_order_total',
            'label' => Mage::helper('payment')->__('Minimum Order Total'),
            'title' => Mage::helper('payment')->__('Minimum Order Total'),
            'class' => 'validate-number',
        ]);

        $fieldset->addField('max_order_total', 'text', [
            'name'  => 'max_order_total',
            'label' => Mage::helper('payment')->__('Maximum Order Total'),
            'title' => Mage::helper('payment')->__('Maximum Order Total'),
            'class' => 'validate-number',
        ]);

        $fieldset->addField('product_categories', 'text', [
            'name'  => 'product_categories',
            'label' => Mage::helper('payment')->__('Product Categories'),
            'title' => Mage::helper('payment')->__('Product Categories'),
            'note'  => Mage::helper('payment')->__('Comma-separated category IDs'),
        ]);

        $fieldset->addField('product_skus', 'textarea', [
            'name'  => 'product_skus',
            'label' => Mage::helper('payment')->__('Product SKUs'),
            'title' => Mage::helper('payment')->__('Product SKUs'),
            'note'  => Mage::helper('payment')->__('Comma-separated SKUs'),
        ]);

        if ($data = Mage::registry('payment_restriction')->getData()) {
            // Convert comma-separated values back to arrays for multiselect fields
            if (isset($data['payment_methods'])) {
                $data['restriction_payment_methods_field'] = explode(',', $data['payment_methods']);
            }
            if (isset($data['customer_groups'])) {
                $data['restriction_customer_groups'] = explode(',', $data['customer_groups']);
            }
            if (isset($data['countries'])) {
                $data['restriction_countries'] = explode(',', $data['countries']);
            }
            if (isset($data['store_ids'])) {
                $data['restriction_store_ids'] = explode(',', $data['store_ids']);
            }

            $form->setValues($data);
        }

        return parent::_prepareForm();
    }

    protected function _getPaymentMethods(): array
    {
        $methods = [];
        $paymentMethods = Mage::helper('payment')->getPaymentMethods();

        foreach ($paymentMethods as $code => $data) {
            $title = isset($data['title']) ? $data['title'] : $code;
            $methods[] = ['value' => $code, 'label' => $title . ' (' . $code . ')'];
        }

        return $methods;
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

    protected function _getCountries(): array
    {
        return Mage::getModel('adminhtml/system_config_source_country')->toOptionArray();
    }

    protected function _getStores(): array
    {
        $stores = [];
        foreach (Mage::app()->getStores() as $store) {
            $stores[] = [
                'value' => $store->getId(),
                'label' => $store->getName() . ' (' . $store->getCode() . ')',
            ];
        }
        return $stores;
    }
}

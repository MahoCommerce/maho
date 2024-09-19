<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Customer EAV Observer
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Model_Customer_Observer
{
    /**
     * Add frontend properties to customer attribute edit form
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function customerAttributeEditPrepareForm($observer)
    {
        /** @var Mage_Customer_Model_Attribute $attribute */
        $attribute = $observer->getAttribute();

        /** @var Varien_Data_Form $form */
        $form = $observer->getForm();

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');

        // frontend properties fieldset
        $fieldset = $form->addFieldset('front_fieldset', ['legend' => Mage::helper('adminhtml')->__('Extra Properties')]);

        $fieldset->addField('use_in_forms', 'multiselect', [
            'name'   => 'use_in_forms',
            'label'  => Mage::helper('adminhtml')->__('Use in Forms'),
            'title'  => Mage::helper('adminhtml')->__('Use in Forms'),
            'values' => Mage::getModel('customer/config_forms')->toOptionArray(),
            'value'  => Mage::getResourceModel('customer/form_attribute')->getFormTypesByAttribute($attribute)
        ]);

        return $this;
    }

    /**
     * Save frontend properties from customer attribute edit form
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function customerAttributeEditPrepareSave($observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getRequest();

        $data = $request->getPost();
        if ($data) {
            /** @var Mage_Eav_Model_Entity_Attribute $model */
            $model = $observer->getObject();

            if (!isset($data['use_in_forms'])) {
                $data['use_in_forms'] = [];
            }

            $model->setData('used_in_forms', $data['use_in_forms']);
        }
        return $this;
    }

    /**
     * Add frontend properties to customer address attribute edit form
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function customerAddressAttributeEditPrepareForm($observer)
    {
        /** @var Mage_Customer_Model_Attribute $attribute */
        $attribute = $observer->getAttribute();

        /** @var Varien_Data_Form $form */
        $form = $observer->getForm();

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');

        // frontend properties fieldset
        $fieldset = $form->addFieldset('front_fieldset', ['legend' => Mage::helper('adminhtml')->__('Extra Properties')]);

        $fieldset->addField('use_in_forms', 'multiselect', [
            'name'   => 'use_in_forms',
            'label'  => Mage::helper('adminhtml')->__('Use in Forms'),
            'title'  => Mage::helper('adminhtml')->__('Use in Forms'),
            'values' => Mage::getModel('customer/config_address_forms')->toOptionArray(),
            'value'  => Mage::getResourceModel('customer/form_attribute')->getFormTypesByAttribute($attribute)
        ]);

        return $this;
    }

    /**
     * Save frontend properties from customer address attribute edit form
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function customerAddressAttributeEditPrepareSave($observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getRequest();

        $data = $request->getPost();
        if ($data) {
            /** @var Mage_Eav_Model_Entity_Attribute $model */
            $model = $observer->getObject();

            if (!isset($data['use_in_forms'])) {
                $data['use_in_forms'] = [];
            }

            $model->setData('used_in_forms', $data['use_in_forms']);
        }
        return $this;
    }
}

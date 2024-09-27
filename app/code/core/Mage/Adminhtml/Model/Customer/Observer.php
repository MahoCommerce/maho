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
     * Modify customer and customer_address attribute edit forms
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function attributeEditPrepareForm($observer)
    {
        /** @var Mage_Customer_Model_Attribute $attribute */
        $attribute = $observer->getAttribute();

        /** @var Varien_Data_Form $form */
        $form = $observer->getForm();

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');

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
     * Save extra properties from customer and customer_address attribute edit forms
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function attributeEditPrepareSave($observer)
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $observer->getRequest();

        /** @var Mage_Eav_Model_Entity_Attribute $attribute */
        $attribute = $observer->getObject();

        $data = $request->getPost();
        if ($data) {
            if (!isset($data['use_in_forms'])) {
                $attribute->setData('used_in_forms', []);
            }
        }

        return $this;
    }
}

<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
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
     * Add input types in customer and customer_address attribute edit forms
     *
     * @param Varien_Event_Observer $observer
     * @return $this
     */
    public function attributeAddInputTypes($observer)
    {
        /** @var Mage_Customer_Model_Attribute $attribute */
        $attribute = $observer->getAttribute();
        $attributeTypeCode = $attribute->getEntityType()->getEntityTypeCode();

        /** @var Varien_Object $response */
        $response = $observer->getResponse();

        $response->setTypes([
            ['value' => 'multiline', 'label' => Mage::helper('eav')->__('Multiline')],
        ]);

        return $this;
    }

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
        $attributeTypeCode = $attribute->getEntityType()->getEntityTypeCode();

        /** @var Varien_Data_Form $form */
        $form = $observer->getForm();

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');

        $fieldset->addField('is_visible', 'select', [
            'name'   => 'is_visible',
            'label'  => Mage::helper('adminhtml')->__('Is Visible'),
            'title'  => Mage::helper('adminhtml')->__('Is Visible'),
            'values' => Mage::getModel('adminhtml/system_config_source_yesno')->toOptionArray(),
        ], 'frontend_class');

        $fieldset->addField('multiline_count', 'number', [
            'name' => 'multiline_count',
            'label' => Mage::helper('eav')->__('Multiline Count'),
            'title' => Mage::helper('eav')->__('Multiline Count'),
            'min' => 1,
            'max' => 4,
        ], 'frontend_input');

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $dependenceBlock */
        $dependenceBlock = $observer->getDependence();

        $dependenceBlock->addFieldMap('frontend_input', 'frontend_input')
                        ->addFieldMap('multiline_count', 'multiline_count')
                        ->addFieldDependence('multiline_count', 'frontend_input', 'multiline');

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

        // $data = $request->getPost();
        // if ($data) {
        //     if (!$attribute->getWebsite()->getId()) {
        //         if (!isset($data['use_in_forms'])) {
        //             $data['use_in_forms'] = [];
        //         }
        //         $attribute->setData('used_in_forms', $data['use_in_forms']);
        //     }
        // }

        return $this;
    }
}

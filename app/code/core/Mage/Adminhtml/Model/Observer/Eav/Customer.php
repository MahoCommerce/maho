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
 */
class Mage_Adminhtml_Model_Observer_Eav_Customer
{
    /**
     * Modify customer and customer_address attribute edit forms
     */
    public function attributeEditPrepareForm(Varien_Event_Observer $observer): self
    {
        /** @var Mage_Customer_Model_Attribute $attribute */
        $attribute = $observer->getAttribute();
        $attributeTypeCode = $attribute->getEntityType()->getEntityTypeCode();

        /** @var Varien_Data_Form $form */
        $form = $observer->getForm();

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->getElement('base_fieldset');

        $fieldset->addField('is_visible', 'boolean', [
            'name'   => 'is_visible',
            'label'  => Mage::helper('adminhtml')->__('Is Visible'),
            'title'  => Mage::helper('adminhtml')->__('Is Visible'),
        ], 'frontend_class');

        $fieldset->addField('multiline_count', 'number', [
            'name' => 'multiline_count',
            'label' => Mage::helper('eav')->__('Multiline Count'),
            'title' => Mage::helper('eav')->__('Multiline Count'),
            'value' => 1,
            'min' => 1,
        ], 'frontend_input');

        if ($attribute->getAttributeCode() === 'street') {
            $form->getElement('multiline_count')
                ->setMin(Mage_Customer_Helper_Address::STREET_LINES_MIN)
                ->setMax(Mage_Customer_Helper_Address::STREET_LINES_MAX);
        }

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $dependenceBlock */
        $dependenceBlock = $observer->getDependence();

        $dependenceBlock->addFieldMap('frontend_input', 'frontend_input')
            ->addFieldMap('multiline_count', 'multiline_count')
            ->addFieldDependence('multiline_count', 'frontend_input', 'multiline');

        return $this;
    }
}

<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit_Tab_Main extends Mage_Eav_Block_Adminhtml_Attribute_Edit_Main_Abstract
{
    /**
     * @return $this
     */
    #[\Override]
    protected function _prepareForm()
    {
        parent::_prepareForm();
        $attributeObject = $this->getAttributeObject();
        $attributeTypeCode = $attributeObject->getEntityType()->getEntityTypeCode();
        /* @var $form Varien_Data_Form */
        $form = $this->getForm();
        /* @var $fieldset Varien_Data_Form_Element_Fieldset */
        $fieldset = $form->getElement('base_fieldset');

        $fieldset->getElements()
                 ->searchById('attribute_code')
                 ->setData(
                     'class',
                     'validate-code-event ' . $fieldset->getElements()->searchById('attribute_code')->getData('class')
                 )->setData(
                     'note',
                     $fieldset->getElements()->searchById('attribute_code')->getData('note')
                     . Mage::helper('eav')->__('. Do not use "event" for an attribute code, it is a reserved keyword.')
                 );

        $fieldset->getElements()
                 ->searchById('is_unique')
                 ->setData(
                     'title',
                     Mage::helper('eav')->__('Unique Value (not shared with other %s)', strtolower(Mage::helper('eav')->formatTypeCode($attributeTypeCode)))
                 )->setData(
                     'note',
                     Mage::helper('eav')->__('Not shared with other %s', strtolower(Mage::helper('eav')->formatTypeCode($attributeTypeCode)))
                 );

        $frontendInputElm = $form->getElement('frontend_input');
        $additionalTypes = [];

        $response = new Varien_Object();
        $response->setTypes([]);
        Mage::dispatchEvent("adminhtml_{$attributeTypeCode}_attribute_types", ['response' => $response]);
        $_disabledTypes = [];
        $_hiddenFields = [];
        foreach ($response->getTypes() as $type) {
            $additionalTypes[] = $type;
            if (isset($type['hide_fields'])) {
                $_hiddenFields[$type['value']] = $type['hide_fields'];
            }
            if (isset($type['disabled_types'])) {
                $_disabledTypes[$type['value']] = $type['disabled_types'];
            }
        }
        Mage::register('attribute_type_hidden_fields', $_hiddenFields);
        Mage::register('attribute_type_disabled_types', $_disabledTypes);

        $frontendInputValues = array_merge($frontendInputElm->getValues(), $additionalTypes);
        $frontendInputElm->setValues($frontendInputValues);

        Mage::dispatchEvent("adminhtml_{$attributeTypeCode}_attribute_edit_prepare_form", [
            'form'      => $form,
            'attribute' => $attributeObject
        ]);

        return $this;
    }
}

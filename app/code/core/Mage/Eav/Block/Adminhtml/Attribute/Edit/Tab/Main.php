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

        /** @var Varien_Data_Form $form */
        $form = $this->getForm();

        /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
        $block = $this->_getDependence();

        Mage::dispatchEvent("adminhtml_{$attributeTypeCode}_attribute_edit_prepare_form", [
            'form'       => $form,
            'attribute'  => $attributeObject,
            'dependence' => $block,
        ]);

        $this->setChild('form_after', $block);

        return $this;
    }
}

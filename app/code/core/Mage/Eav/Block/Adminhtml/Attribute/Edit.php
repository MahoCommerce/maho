<?php
/**
 * Maho
 *
 * @category  Mage
 * @package   Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'attribute_id';
        $this->_blockGroup = 'eav';
        $this->_controller = 'adminhtml_attribute';

        parent::__construct();

        $this->_addButton(
            'save_and_edit_button',
            [
                'label'     => Mage::helper('eav')->__('Save and Continue Edit'),
                'onclick'   => 'saveAndContinueEdit()',
                'class'     => 'save'
            ],
            100
        );

        $this->_updateButton('save', 'label', Mage::helper('eav')->__('Save Attribute'));
        $this->_updateButton('save', 'onclick', 'saveAttribute()');

        if (!Mage::registry('entity_attribute')->getIsUserDefined()) {
            $this->_removeButton('delete');
        } else {
            $this->_updateButton('delete', 'label', Mage::helper('eav')->__('Delete Attribute'));
        }
    }

    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('entity_attribute')->getId()) {
            $frontendLabel = Mage::registry('entity_attribute')->getFrontendLabel();
            if (is_array($frontendLabel)) {
                $frontendLabel = $frontendLabel[0];
            }
            return Mage::helper('eav')->__('Edit %s Attribute "%s"', Mage::helper('eav')->formatTypeCode(Mage::registry('entity_type')), $this->escapeHtml($frontendLabel));
        } else {
            return Mage::helper('eav')->__('New %s Attribute', Mage::helper('eav')->formatTypeCode(Mage::registry('entity_type')));
        }
    }

    public function getValidationUrl()
    {
        return $this->getUrl('*/*/validate', ['_current' => true]);
    }

    #[\Override]
    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save', ['_current' => true, 'back' => null]);
    }
}

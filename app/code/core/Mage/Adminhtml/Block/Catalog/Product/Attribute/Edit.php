<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product attribute edit page
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Catalog_Product_Attribute_Edit extends Mage_Eav_Block_Adminhtml_Attribute_Edit
{
    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');
        $this->entityAttribute = Mage::registry('entity_attribute');

        $this->_objectId = 'attribute_id';
        $this->_blockGroup = 'adminhtml';
        $this->_controller = 'catalog_product_attribute';

        Mage_Adminhtml_Block_Widget_Form_Container::__construct();

        if ($this->getRequest()->getParam('popup')) {
            $this->_removeButton('back');
            $this->_addButton('close', [
                'label'     => Mage::helper('catalog')->__('Close Window'),
                'class'     => 'cancel',
                'onclick'   => 'window.close()',
                'level'     => -1
            ]);
        } else {
            $this->_addButton('save_and_edit_button', [
                'label'     => Mage::helper('catalog')->__('Save and Continue Edit'),
                'onclick'   => 'saveAndContinueEdit()',
                'class'     => 'save'
            ], 100);
        }

        $this->_updateButton('save', 'label', Mage::helper('catalog')->__('Save Attribute'));
        $this->_updateButton('save', 'onclick', 'saveAttribute()');

        if ($this->entityAttribute->getIsUserDefined()) {
            $this->_updateButton('delete', 'label', $this->__('Delete Attribute'));
        } else {
            $this->_removeButton('delete');
        }
    }
}

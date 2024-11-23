<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml generic EAV attribute edit page
 *
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    protected Mage_Eav_Model_Entity_Type $entityType;
    protected Mage_Eav_Model_Entity_Attribute $entityAttribute;

    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');
        $this->entityAttribute = Mage::registry('entity_attribute');

        $this->_objectId = 'attribute_id';
        $this->_blockGroup = 'eav';
        $this->_controller = 'adminhtml_attribute';

        parent::__construct();

        $this->_addButton('save_and_edit_button', [
            'label'   => $this->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class'   => 'save'
        ], 100);

        $this->_updateButton('save', 'label', $this->__('Save Attribute'));
        $this->_updateButton('save', 'onclick', 'saveAttribute()');

        if ($this->entityAttribute->getIsUserDefined()) {
            $this->_updateButton('delete', 'label', $this->__('Delete Attribute'));
        } else {
            $this->_removeButton('delete');
        }
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if ($this->entityAttribute->getId()) {
            return $this->__(
                'Edit %s Attribute "%s"',
                Mage::helper('eav')->formatTypeCode($this->entityType->getEntityTypeCode()),
                $this->entityAttribute->getFrontendLabel()
            );
        }
        return $this->__(
            'New %s Attribute',
            Mage::helper('eav')->formatTypeCode($this->entityType->getEntityTypeCode()),
        );
    }

    /**
     * @return string
     */
    public function getValidationUrl()
    {
        return $this->getUrl('*/*/validate', ['_current' => true]);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save', ['_current' => true, 'back' => null]);
    }
}

<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Variable_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_objectId = 'variable_id';
        $this->_controller = 'system_variable';
    }

    /**
     * @return Mage_Core_Model_Variable
     */
    public function getVariable()
    {
        return Mage::registry('current_variable');
    }

    #[\Override]
    protected function _preparelayout()
    {
        $this->_addButton('save_and_edit', [
            'label'     => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'class'     => 'save',
            'onclick'   => 'editForm.submit(\'' . $this->getSaveAndContinueUrl() . '\');',
        ], 100);
        if (!$this->getVariable()->getId()) {
            $this->removeButton('delete');
        }
        return parent::_prepareLayout();
    }

    /**
     * Return form HTML
     *
     * @return string
     */
    #[\Override]
    public function getFormHtml()
    {
        $formHtml = parent::getFormHtml();
        if (!Mage::app()->isSingleStoreMode() && $this->getVariable()->getId()) {
            $storeSwitcher = $this->getLayout()
                ->createBlock('adminhtml/store_switcher')->toHtml();
            $formHtml = $storeSwitcher . $formHtml;
        }
        return $formHtml;
    }

    /**
     * Return translated header text depending on creating/editing action
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if ($this->getVariable()->getId()) {
            return Mage::helper('adminhtml')->__('Custom Variable "%s"', $this->escapeHtml($this->getVariable()->getName()));
        }
        return Mage::helper('adminhtml')->__('New Custom Variable');
    }

    /**
     * Return validation url for edit form
     *
     * @return string
     */
    public function getValidationUrl()
    {
        return $this->getUrl('*/*/validate', ['_current' => true]);
    }

    /**
     * Return save url for edit form
     *
     * @return string
     */
    #[\Override]
    public function getSaveUrl()
    {
        return $this->getUrl('*/*/save', ['_current' => true, 'back' => null]);
    }

    /**
     * Return save and continue url for edit form
     *
     * @return string
     */
    public function getSaveAndContinueUrl()
    {
        return $this->getUrl('*/*/save', ['_current' => true, 'back' => 'edit']);
    }
}

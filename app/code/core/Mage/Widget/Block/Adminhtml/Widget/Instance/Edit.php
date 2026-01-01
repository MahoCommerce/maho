<?php

/**
 * Maho
 *
 * @package    Mage_Widget
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Widget_Block_Adminhtml_Widget_Instance_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_objectId = 'instance_id';
        $this->_blockGroup = 'widget';
        $this->_controller = 'adminhtml_widget_instance';
    }

    /**
     * Getter
     *
     * @return Mage_Widget_Model_Widget_Instance
     */
    public function getWidgetInstance()
    {
        return Mage::registry('current_widget_instance');
    }

    /**
     * Adding save_and_continue button
     */
    #[\Override]
    protected function _prepareLayout()
    {
        if ($this->getWidgetInstance()->isCompleteToCreate()) {
            $this->_addButton(
                'save_and_edit_button',
                [
                    'label'     => Mage::helper('widget')->__('Save and Continue Edit'),
                    'class'     => 'save',
                    'onclick'   => 'saveAndContinueEdit()',
                ],
                100,
            );
        } else {
            $this->removeButton('save');
        }
        return parent::_prepareLayout();
    }

    /**
     * Return translated header text depending on creating/editing action
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if ($this->getWidgetInstance()->getId()) {
            return Mage::helper('widget')->__('Widget "%s"', $this->escapeHtml($this->getWidgetInstance()->getTitle()));
        }
        return Mage::helper('widget')->__('New Widget Instance');
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
}

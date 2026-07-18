<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Group_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_controller = 'customer_group';

        $this->_updateButton('save', 'label', Mage::helper('customer')->__('Save Customer Group'));
        $this->_updateButton('delete', 'label', Mage::helper('customer')->__('Delete Customer Group'));

        if (!Mage::registry('current_group')->getId() || Mage::registry('current_group')->usesAsDefault()) {
            $this->_removeButton('delete');
        }
    }

    /**
     * @return string
     * @throws Exception
     */
    #[\Override]
    public function getDeleteUrl()
    {
        if (!Mage::getSingleton('adminhtml/url')->useSecretKey()) {
            return $this->getUrl('*/*/delete', [
                $this->_objectId => $this->getRequest()->getParam($this->_objectId),
                'form_key' => Mage::getSingleton('core/session')->getFormKey(),
            ]);
        }
        return parent::getDeleteUrl();
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (!is_null(Mage::registry('current_group')->getId())) {
            return Mage::helper('customer')->__('Edit Customer Group "%s"', $this->escapeHtml(Mage::registry('current_group')->getCustomerGroupCode()));
        }
        return Mage::helper('customer')->__('New Customer Group');
    }
}

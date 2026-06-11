<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Edit_Renderer_Attribute_Group extends Mage_Adminhtml_Block_Widget_Form_Renderer_Fieldset_Element
{
    /**
     * Override parent constructor just for setting custom template
     */
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('customer/edit/tab/account/form/renderer/group.phtml');
    }

    /**
     * Retrieve disable auto group change element HTML ID
     *
     * @return string
     */
    protected function _getDisableAutoGroupChangeElementHtmlId()
    {
        return $this->getDisableAutoGroupChangeAttribute()->getAttributeCode();
    }

    /**
     * Retrieve disable auto group change checkbox label text
     *
     * @return string
     */
    public function getDisableAutoGroupChangeCheckboxLabel()
    {
        return Mage::helper('customer')->__($this->getDisableAutoGroupChangeAttribute()->getFrontend()->getLabel());
    }

    /**
     * Retrieve disable auto group change checkbox state
     *
     * @return string
     */
    public function getDisableAutoGroupChangeCheckboxState()
    {
        $customer = Mage::registry('current_customer');
        $checkedByDefault = ($customer && $customer->getId())
            ? false : Mage::helper('customer/address')->getDisableAutoGroupAssignDefaultValue();

        $value = $this->getDisableAutoGroupChangeAttributeValue();
        $state = '';
        if (!empty($value) || $checkedByDefault) {
            $state = 'checked';
        }
        return $state;
    }

    /**
     * Retrieve disable auto group change checkbox element HTML NAME
     *
     * @return string
     */
    public function getDisableAutoGroupChangeCheckboxElementName()
    {
        return $this->getElement()->getForm()->getFieldNameSuffix()
            . '[' . $this->_getDisableAutoGroupChangeElementHtmlId() . ']';
    }

    /**
     * Retrieve disable auto group change checkbox element HTML ID
     *
     * @return string
     */
    public function getDisableAutoGroupChangeCheckboxElementId()
    {
        return $this->_getDisableAutoGroupChangeElementHtmlId();
    }
}

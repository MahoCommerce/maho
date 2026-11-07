<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Catalog_Product_Attribute_Set_Main extends Mage_Eav_Block_Adminhtml_Attribute_Set_Edit
{
    #[\Override]
    protected function _construct()
    {
        Mage::helper('core/js')->addTranslateData([
            'All products of this set will be deleted! Type "confirm" to proceed.',
            'Cannot delete group. Please move configurable attributes to another group and try again.',
            'Cannot unassign configurable attribute',
        ], 'catalog');

        $this->setTemplate('catalog/product/attribute/set/main.phtml');
        $this->setIsReadOnly(false);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        $this->setChild(
            'edit_set_form',
            $this->getLayout()->createBlock('adminhtml/catalog_product_attribute_set_main_formset'),
        );
        return $this;
    }

    #[\Override]
    protected function _getHeader()
    {
        return Mage::helper('catalog')->__("Edit Attribute Set '%s'", $this->_getAttributeSet()->getAttributeSetName());
    }

    #[\Override]
    protected function _getAttributeCollection(): Mage_Eav_Model_Resource_Entity_Attribute_Collection
    {
        return Mage::getResourceModel('catalog/product_attribute_collection')->addVisibleFilter();
    }

    #[\Override]
    protected function _getConfigurableAttributeIds(): array
    {
        return Mage::getResourceModel('catalog/product_type_configurable_attribute')
            ->getUsedAttributes($this->_getSetId());
    }

    #[\Override]
    protected function _toHtml()
    {
        Mage::dispatchEvent('adminhtml_catalog_product_attribute_set_main_html_before', ['block' => $this]);
        return parent::_toHtml();
    }
}

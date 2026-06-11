<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Resource_Category_Attribute_Source_Page extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Return all block options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('cms/block_collection')
                ->load()
                ->toOptionArray();
            array_unshift($this->_options, ['value' => '', 'label' => Mage::helper('catalog')->__('Please select a static block ...')]);
        }
        return $this->_options;
    }
}

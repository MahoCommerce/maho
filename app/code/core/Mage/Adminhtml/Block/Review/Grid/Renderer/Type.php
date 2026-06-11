<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Review_Grid_Renderer_Type extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Mage_Catalog_Model_Product $row
     * @return string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        if (is_null($row->getCustomerId())) {
            if ($row->getStoreId() == Mage_Core_Model_App::ADMIN_STORE_ID) {
                return Mage::helper('review')->__('Administrator');
            }
            return Mage::helper('review')->__('Guest');
        }

        if ($row->getCustomerId() > 0) {
            return Mage::helper('review')->__('Customer');
        }

        return '';
    }
}

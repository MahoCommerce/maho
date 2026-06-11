<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Customer_Group_Multiselect
{
    /**
     * Customer groups options array
     *
     * @var null|array
     */
    protected $_options;

    public function toOptionArray(): array
    {
        if (!$this->_options) {
            $this->_options = Mage::getResourceModel('customer/group_collection')
                ->setRealGroupsFilter()
                ->loadData()->toOptionArray();
        }
        return $this->_options;
    }
}

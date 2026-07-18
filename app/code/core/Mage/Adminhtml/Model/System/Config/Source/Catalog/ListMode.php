<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Catalog_ListMode
{
    public function toOptionArray(): array
    {
        return [
            //array('value'=>'', 'label'=>''),
            ['value' => 'grid', 'label' => Mage::helper('adminhtml')->__('Grid Only')],
            ['value' => 'list', 'label' => Mage::helper('adminhtml')->__('List Only')],
            ['value' => 'grid-list', 'label' => Mage::helper('adminhtml')->__('Grid (default) / List')],
            ['value' => 'list-grid', 'label' => Mage::helper('adminhtml')->__('List (default) / Grid')],
        ];
    }
}

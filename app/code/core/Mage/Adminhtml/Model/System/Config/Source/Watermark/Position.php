<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Watermark_Position
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'stretch',         'label' => Mage::helper('catalog')->__('Stretch')],
            ['value' => 'tile',            'label' => Mage::helper('catalog')->__('Tile')],
            ['value' => 'top-left',        'label' => Mage::helper('catalog')->__('Top/Left')],
            ['value' => 'top-right',       'label' => Mage::helper('catalog')->__('Top/Right')],
            ['value' => 'bottom-left',     'label' => Mage::helper('catalog')->__('Bottom/Left')],
            ['value' => 'bottom-right',    'label' => Mage::helper('catalog')->__('Bottom/Right')],
            ['value' => 'center',          'label' => Mage::helper('catalog')->__('Center')],
        ];
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash
{
    public const REMOVE_TRAILING_SLASH = 'remove';
    public const ADD_TRAILING_SLASH = 'add';
    public const DO_NOTHING = 'nothing';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::REMOVE_TRAILING_SLASH, 'label' => Mage::helper('adminhtml')->__('Redirect to URL without trailing slash')],
            ['value' => self::ADD_TRAILING_SLASH, 'label' => Mage::helper('adminhtml')->__('Redirect to URL with trailing slash')],
            ['value' => self::DO_NOTHING, 'label' => Mage::helper('adminhtml')->__('Do nothing')],
        ];
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Helper_Addresses extends Mage_Core_Helper_Abstract
{
    public const DEFAULT_STREET_LINES_COUNT = 2;

    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * Check if number of street lines is non-zero
     *
     * @return Mage_Customer_Model_Attribute
     */
    public function processStreetAttribute(Mage_Customer_Model_Attribute $attribute)
    {
        if ($attribute->getScopeMultilineCount() <= 0) {
            $attribute->setScopeMultilineCount(self::DEFAULT_STREET_LINES_COUNT);
        }
        return $attribute;
    }
}

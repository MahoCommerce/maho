<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Sitemap extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if ($value < 0 || $value > 1) {
            throw new Exception(Mage::helper('sitemap')->__('The priority must be between 0 and 1.'));
        }
        if (($value == 0) && !($value === '0' || $value === '0.0')) {
            throw new Exception(Mage::helper('sitemap')->__('The priority must be between 0 and 1.'));
        }
        return $this;
    }
}

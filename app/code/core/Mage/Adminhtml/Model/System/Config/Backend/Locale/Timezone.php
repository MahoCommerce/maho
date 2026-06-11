<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Locale_Timezone extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $allWithBc = DateTimeZone::ALL_WITH_BC;
        if (!in_array($this->getValue(), DateTimeZone::listIdentifiers($allWithBc))) {
            Mage::throwException(Mage::helper('adminhtml')->__('Invalid timezone'));
        }

        return $this;
    }
}

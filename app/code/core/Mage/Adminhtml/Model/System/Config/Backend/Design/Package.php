<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_System_Config_Backend_Design_Package extends Mage_Core_Model_Config_Data
{
    #[\Override]
    protected function _beforeSave()
    {
        $value = $this->getValue();
        if (empty($value)) {
            throw new Exception('package name is empty.');
        }
        if (!Mage::getDesign()->designPackageExists($value)) {
            throw new Exception('package with this name does not exist and cannot be set.');
        }
        return $this;
    }
}

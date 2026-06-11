<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Install
 */

declare(strict_types=1);

use Maho\DataObject;

abstract class Mage_Install_Block_Abstract extends Mage_Core_Block_Template
{
    public function getInstaller(): Mage_Install_Model_Installer
    {
        return Mage::getSingleton('install/installer');
    }

    public function getWizard(): Mage_Install_Model_Wizard
    {
        return Mage::getSingleton('install/wizard');
    }

    public function getCurrentStep(): DataObject
    {
        return $this->getWizard()->getStepByRequest($this->getRequest());
    }
}

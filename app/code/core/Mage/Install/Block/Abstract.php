<?php

/**
 * Maho
 *
 * @package    Mage_Install
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

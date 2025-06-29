<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag('adminactivitylog/general/enabled');
    }

    public function cleanOldLogs(): void
    {
        if ($this->isEnabled()) {
            Mage::getModel('adminactivitylog/activity')->cleanOldLogs();
            Mage::getModel('adminactivitylog/login')->cleanOldLogs();
        }
    }
}

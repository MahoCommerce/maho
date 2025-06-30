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
        return Mage::getStoreConfigFlag('admin/adminactivitylog/enabled');
    }

    public function shouldLogActivity(): bool
    {
        return $this->isEnabled() && Mage::getSingleton('admin/session')->isLoggedIn();
    }

    public function shouldLogPageVisit(): bool
    {
        return $this->shouldLogActivity() && Mage::getStoreConfigFlag('admin/adminactivitylog/log_page_visit');
    }

    public function shouldLogSaveActions(): bool
    {
        return $this->shouldLogActivity() && Mage::getStoreConfigFlag('admin/adminactivitylog/log_save_actions');
    }

    public function shouldLogDeleteActions(): bool
    {
        return $this->shouldLogActivity() && Mage::getStoreConfigFlag('admin/adminactivitylog/log_delete_actions');
    }

    public function shouldLogMassActions(): bool
    {
        return $this->shouldLogActivity() && Mage::getStoreConfigFlag('admin/adminactivitylog/log_mass_actions');
    }

    public function shouldLogAuth(): bool
    {
        return $this->isEnabled() && Mage::getStoreConfigFlag('admin/adminactivitylog/log_login_activity');
    }

    public function shouldLogFailedAuth(): bool
    {
        return $this->isEnabled() && Mage::getStoreConfigFlag('admin/adminactivitylog/log_failed_login');
    }

    public function cleanOldLogs(): void
    {
        if ($this->isEnabled()) {
            Mage::getResourceModel('adminactivitylog/activity')->cleanOldLogs();
            Mage::getResourceModel('adminactivitylog/login')->cleanOldLogs();
        }
    }
}

<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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

    public function getDaysToKeepLogs(): int
    {
        return Mage::getStoreConfigAsInt('admin/adminactivitylog/days_to_keep');
    }

    public function getDbFields(mixed $object): ?array
    {
        try {
            $resource = $object->getResource();
            if ($resource instanceof Mage_Eav_Model_Entity_Abstract) {
                return array_keys($resource->getAttributesByCode());
            }
            if ($resource instanceof Mage_Core_Model_Resource_Db_Abstract) {
                return array_keys($resource->getReadConnection()->describeTable($resource->getMainTable()));
            }
        } catch (Throwable $e) {
        }
        return null;
    }

    public function cleanOldLogs(): void
    {
        Mage::getResourceModel('adminactivitylog/activity')->cleanOldLogs();
        Mage::getResourceModel('adminactivitylog/login')->cleanOldLogs();
    }
}

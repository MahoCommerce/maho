<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

class Maho_AdminActivityLog_Model_Resource_Login extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('adminactivitylog/login', 'login_id');
    }

    public function cleanOldLogs(): void
    {
        $daysToKeep = Mage::helper('adminactivitylog')->getDaysToKeepLogs();
        if ($daysToKeep > 0) {
            $date = Mage::app()->getLocale()->formatDateForDb("-{$daysToKeep} days");
            Mage::getResourceModel('adminactivitylog/login_collection')
                ->addFieldToFilter('created_at', ['lt' => $date])
                ->walk('delete');
        }
    }
}

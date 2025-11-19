<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
            $date = Mage::getModel('core/date')->gmtDate(Mage_Core_Model_Locale::DATETIME_FORMAT, strtotime("-{$daysToKeep} days"));
            Mage::getResourceModel('adminactivitylog/login_collection')
                ->addFieldToFilter('created_at', ['lt' => $date])
                ->walk('delete');
        }
    }
}

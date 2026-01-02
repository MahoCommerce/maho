<?php

/**
 * Maho
 *
 * @package    Mage_Cron
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cron_Model_Resource_Schedule extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('cron/schedule', 'schedule_id');
    }

    /**
     * If job is currently in $currentStatus, set it to $newStatus
     * and return true. Otherwise, return false and do not change the job.
     * This method is used to implement locking for cron jobs.
     *
     * @param int $scheduleId
     * @param String $newStatus
     * @param String $currentStatus
     * @return bool
     */
    public function trySetJobStatusAtomic($scheduleId, $newStatus, $currentStatus)
    {
        $write = $this->_getWriteAdapter();
        $result = $write->update(
            $this->getTable('cron/schedule'),
            ['status' => $newStatus],
            ['schedule_id = ?' => $scheduleId, 'status = ?' => $currentStatus],
        );
        if ($result == 1) {
            return true;
        }
        return false;
    }
}

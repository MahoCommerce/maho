<?php

/**
 * Collection of the saved reports.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_SavedReport_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/savedReport');
    }

    /** Keep the shared reports and the reports of $adminUserId. Without an administrator, keep the shared ones. */
    public function addVisibilityFilter(?int $adminUserId): static
    {
        if ($adminUserId === null) {
            $this->addFieldToFilter('is_shared', 1);
            return $this;
        }
        $this->getSelect()->where('main_table.is_shared = 1 OR main_table.admin_user_id = ?', $adminUserId);
        return $this;
    }
}

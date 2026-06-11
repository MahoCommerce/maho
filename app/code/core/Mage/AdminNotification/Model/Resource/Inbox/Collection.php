<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_AdminNotification
 */

declare(strict_types=1);

class Mage_AdminNotification_Model_Resource_Inbox_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Resource collection initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('adminnotification/inbox');
    }

    /**
     * Add remove filter
     *
     * @return $this
     */
    public function addRemoveFilter()
    {
        $this->getSelect()
            ->where('is_remove=?', 0);
        return $this;
    }
}

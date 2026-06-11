<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

class Mage_Dataflow_Model_Resource_Profile_History_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Define resource model and model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/profile_history');
    }

    /**
     * Joins admin data to select
     *
     * @return $this
     */
    public function joinAdminUser()
    {
        $this->getSelect()->join(
            ['u' => $this->getTable('admin/user')],
            'u.user_id=main_table.user_id',
            ['firstname', 'lastname'],
        );
        return $this;
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

class Mage_Dataflow_Model_Resource_Batch_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/batch');
    }

    /**
     * Add expire filter (for abandoned batches)
     */
    public function addExpireFilter()
    {
        $lifetime = Mage_Dataflow_Model_Batch::LIFETIME;
        $expire   = Mage::app()->getLocale()->formatDateForDb(time() - $lifetime);

        $this->getSelect()->where('created_at < ?', $expire);
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

class Mage_Downloadable_Model_Resource_Link_Purchased_Item extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('downloadable/link_purchased_item', 'item_id');
    }

    /**
     * Take one download from the purchased count in a single conditional
     * UPDATE, expiring the link when the last one is taken. Returns false when
     * the link is not available or has no downloads left.
     */
    public function reserveDownload(int $itemId): bool
    {
        $adapter = $this->_getWriteAdapter();
        $expired = $adapter->quote(Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_EXPIRED);

        // status comes first: MySQL applies SET assignments in order, so the
        // counter must still hold its old value while the status is computed.
        $updated = $adapter->update(
            $this->getMainTable(),
            [
                'status' => new Maho\Db\Expr(
                    'CASE WHEN number_of_downloads_bought > 0 AND number_of_downloads_used + 1 >= number_of_downloads_bought'
                    . ' THEN ' . $expired . ' ELSE status END',
                ),
                'number_of_downloads_used' => new Maho\Db\Expr('number_of_downloads_used + 1'),
                'updated_at' => Mage::app()->getLocale()->formatDateForDb('now'),
            ],
            [
                'item_id = ?' => $itemId,
                'status = ?' => Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE,
                '(number_of_downloads_bought = 0 OR number_of_downloads_used < number_of_downloads_bought)',
            ],
        );

        return $updated > 0;
    }

    /**
     * Give a reserved download back after the content could not be sent.
     */
    public function releaseDownload(int $itemId): void
    {
        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            [
                'number_of_downloads_used' => new Maho\Db\Expr('number_of_downloads_used - 1'),
                'status' => Mage_Downloadable_Model_Link_Purchased_Item::LINK_STATUS_AVAILABLE,
                'updated_at' => Mage::app()->getLocale()->formatDateForDb('now'),
            ],
            [
                'item_id = ?' => $itemId,
                'number_of_downloads_used > 0',
            ],
        );
    }
}

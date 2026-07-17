<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

class Mage_Downloadable_Model_Resource_Link_Purchased_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('downloadable/link_purchased');
    }

    /**
     * Add purchased items to collection
     *
     * @return $this
     */
    public function addPurchasedItemsToResult()
    {
        $this->getSelect()
            ->join(
                ['pi' => $this->getTable('downloadable/link_purchased_item')],
                'pi.purchased_id=main_table.purchased_id',
            );
        return $this;
    }
}

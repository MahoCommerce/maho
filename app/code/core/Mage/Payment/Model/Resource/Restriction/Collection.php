<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

class Mage_Payment_Model_Resource_Restriction_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('payment/restriction');
    }

    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter('status', Mage_Payment_Model_Restriction::STATUS_ENABLED);
    }

    public function addStoreFilter(int $storeId): self
    {
        return $this->addFieldToFilter([
            ['attribute' => 'store_ids', 'like' => '%' . $storeId . '%'],
            ['attribute' => 'store_ids', 'eq' => ''],
            ['attribute' => 'store_ids', 'null' => true],
        ]);
    }
}

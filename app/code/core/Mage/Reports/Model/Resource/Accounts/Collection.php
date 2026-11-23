<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_Accounts_Collection extends Mage_Reports_Model_Resource_Customer_Collection implements Mage_Reports_Model_Report_CollectionInterface
{
    /**
     * Join created_at and accounts fields
     *
     * @param string $from
     * @param string $to
     * @return $this
     */
    protected function _joinFields($from = '', $to = '')
    {
        $this->getSelect()->reset(Maho\Db\Select::COLUMNS);
        $this->addAttributeToFilter('created_at', ['from' => $from, 'to' => $to, 'datetime' => true])
             ->addExpressionAttributeToSelect('accounts', 'COUNT({{entity_id}})', ['entity_id']);

        $this->getSelect()->having("{$this->_joinFields['accounts']['field']} > ?", 0);

        return $this;
    }

    /**
     * Set date range
     */
    #[\Override]
    public function setDateRange(string $from, string $to): static
    {
        $this->_reset()
             ->_joinFields($from, $to);
        return $this;
    }

    /**
     * Set store ids to final result
     *
     * @param list<int>|null $storeIds
     */
    #[\Override]
    public function setStoreIds(?array $storeIds): static
    {
        if ($storeIds) {
            $this->addAttributeToFilter('store_id', ['in' => (array) $storeIds]);
        }
        return $this;
    }
}

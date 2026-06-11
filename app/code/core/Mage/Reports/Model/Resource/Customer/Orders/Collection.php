<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

class Mage_Reports_Model_Resource_Customer_Orders_Collection extends Mage_Reports_Model_Resource_Order_Collection
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_useAnalyticFunction = true;
    }
    /**
     * Join fields
     *
     * @param string $from
     * @param string $to
     * @return $this
     */
    protected function _joinFields($from = '', $to = '')
    {
        $this->joinCustomerName()
            ->groupByCustomer()
            ->addOrdersCount()
            ->addAttributeToFilter('created_at', ['from' => $from, 'to' => $to, 'datetime' => true]);
        return $this;
    }

    /**
     * Set date range
     *
     * @param string $from
     * @param string $to
     * @return $this
     */
    #[\Override]
    public function setDateRange($from, $to)
    {
        $this->_reset()
            ->_joinFields($from, $to);
        return $this;
    }

    /**
     * Set store filter to collection
     *
     * @param array $storeIds
     * @return $this
     */
    #[\Override]
    public function setStoreIds($storeIds)
    {
        if ($storeIds) {
            $this->addAttributeToFilter('store_id', ['in' => (array) $storeIds]);
            $this->addSumAvgTotals(1)
                ->orderByOrdersCount();
        } else {
            $this->addSumAvgTotals()
                ->orderByOrdersCount();
        }

        return $this;
    }
}

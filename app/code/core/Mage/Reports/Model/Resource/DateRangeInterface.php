<?php

/**
 * A report collection that Mage_Reports_Model_Report filters by date range and store.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

interface Mage_Reports_Model_Resource_DateRangeInterface
{
    /**
     * @param string $from
     * @param string $to
     * @return $this
     */
    public function setDateRange($from, $to);

    /**
     * @param array $storeIds
     * @return $this
     */
    public function setStoreIds($storeIds);
}

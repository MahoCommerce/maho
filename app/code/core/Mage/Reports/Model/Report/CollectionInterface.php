<?php

/**
 * A collection that Mage_Reports_Model_Report filters by a date range and by stores.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

interface Mage_Reports_Model_Report_CollectionInterface
{
    /**
     * Limit the collection to the records from $from to $to, both UTC 'Y-m-d H:i:s' strings
     */
    public function setDateRange(string $from, string $to): static;

    /**
     * Limit the collection to the stores $storeIds. Null or an empty list keeps all stores.
     *
     * @param list<int>|null $storeIds
     */
    public function setStoreIds(?array $storeIds): static;
}

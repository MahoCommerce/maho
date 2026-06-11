<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Model_Resource_Rule_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('cataloglinkrule/rule');
    }

    /**
     * Add date filter to only include active rules within date range
     *
     * @return $this
     */
    public function addDateFilter(): self
    {
        // from_date/to_date are admin-entered as store-local — compare in store TZ
        $now = Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

        // Quote from_date/to_date: newer MariaDB reserves them as date functions, so
        // unquoted column references fail to parse.
        $connection = $this->getConnection();
        $fromDate = $connection->quoteIdentifier('from_date');
        $toDate = $connection->quoteIdentifier('to_date');
        $this->getSelect()
            ->where("$fromDate IS NULL OR $fromDate <= ?", $now)
            ->where("$toDate IS NULL OR $toDate >= ?", $now);

        return $this;
    }
}

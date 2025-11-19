<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        $now = Mage_Core_Model_Locale::now();

        $this->getSelect()
            ->where('from_date IS NULL OR from_date <= ?', $now)
            ->where('to_date IS NULL OR to_date >= ?', $now);

        return $this;
    }
}

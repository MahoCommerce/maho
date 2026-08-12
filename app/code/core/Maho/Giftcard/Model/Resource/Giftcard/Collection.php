<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Model_Resource_Giftcard_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('giftcard/giftcard');
    }

    /**
     * Filter by active status
     *
     * @return $this
     */
    public function addActiveFilter(): self
    {
        $this->addFieldToFilter('status', Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE);
        return $this;
    }

    /**
     * Filter by order ID
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): self
    {
        $this->addFieldToFilter('purchase_order_id', $orderId);
        return $this;
    }

    /**
     * Filter to cards valid on the given website, by membership in the
     * giftcard_website junction. A correlated subquery instead of a join, so
     * the filter composes with unqualified addFieldToFilter() column names
     * (a join would make shared columns like giftcard_id ambiguous).
     *
     * @return $this
     */
    public function addWebsiteFilter(int $websiteId): self
    {
        $this->getSelect()->where(
            'main_table.giftcard_id IN (SELECT giftcard_id FROM '
            . $this->getTable('giftcard/website') . ' WHERE website_id = ?)',
            $websiteId,
        );
        return $this;
    }

    /**
     * Filter to cards valid on any of the given websites. An empty list matches
     * nothing, so a token scoped to no website sees no cards.
     *
     * @param int[] $websiteIds
     * @return $this
     */
    public function addWebsiteIdsFilter(array $websiteIds): self
    {
        $this->getSelect()->where(
            'main_table.giftcard_id IN (SELECT giftcard_id FROM '
            . $this->getTable('giftcard/website') . ' WHERE website_id IN (?))',
            $websiteIds === [] ? [-1] : array_map(intval(...), $websiteIds),
        );
        return $this;
    }
}

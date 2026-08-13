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
     * @return $this
     */
    public function addWebsiteFilter(int $websiteId): self
    {
        return $this->addWebsiteIdsFilter([$websiteId]);
    }

    /**
     * Membership subquery (a join would make giftcard_id ambiguous for
     * addFieldToFilter). An empty list matches nothing, not everything.
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

    /**
     * Hydrate each row's `website_ids` as an aggregated CSV the model's
     * getWebsiteIds() parses, avoiding a junction query per loaded card.
     * Correlated subquery (not a join + GROUP BY) so paging and the pager's
     * count query stay on plain rows; orphaned cards still appear (NULL alias).
     *
     * @return $this
     */
    public function addWebsiteIdsToSelect(): self
    {
        $adapter = $this->getConnection();
        $this->getSelect()->columns([
            'website_ids' => new Maho\Db\Expr(sprintf(
                '(SELECT %s FROM %s gw WHERE gw.giftcard_id = main_table.giftcard_id)',
                $adapter->getGroupConcatExpr('gw.website_id'),
                $this->getTable('giftcard/website'),
            )),
        ]);
        return $this;
    }
}

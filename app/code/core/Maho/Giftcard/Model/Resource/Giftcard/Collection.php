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
}

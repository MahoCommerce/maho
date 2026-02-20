<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

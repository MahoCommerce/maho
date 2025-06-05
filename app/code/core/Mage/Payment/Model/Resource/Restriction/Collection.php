<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Model_Resource_Restriction_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    protected function _construct(): void
    {
        $this->_init('payment/restriction');
    }

    public function addActiveFilter(): self
    {
        return $this->addFieldToFilter('status', Mage_Payment_Model_Restriction::STATUS_ENABLED);
    }

    public function addPaymentMethodFilter(string $paymentMethod): self
    {
        return $this->addFieldToFilter([
            ['attribute' => 'payment_methods', 'like' => '%' . $paymentMethod . '%'],
            ['attribute' => 'payment_methods', 'eq' => ''],
            ['attribute' => 'payment_methods', 'null' => true],
        ]);
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

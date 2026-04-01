<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Pos
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Pos_Model_Resource_Payment extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('maho_pos/payment', 'entity_id');
    }

    /**
     * Get total paid amount for an order
     */
    public function getTotalPaidAmount(int $orderId): float
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['total' => new \Maho\Db\Expr('COALESCE(SUM(amount), 0)')])
            ->where('order_id = ?', $orderId);

        return (float) $adapter->fetchOne($select);
    }
}

<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Pos
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Maho_Pos_Model_Resource_Payment_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('maho_pos/payment');
    }

    /**
     * Filter by order ID
     *
     * @return $this
     */
    public function addOrderFilter(int $orderId): static
    {
        $this->addFieldToFilter('order_id', $orderId);
        return $this;
    }
}

<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Order status collection
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Entity_Order_Payment_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_payment');
    }

    /**
     * @param int $orderId
     * @return $this
     */
    public function setOrderFilter($orderId)
    {
        $this->addAttributeToFilter('parent_id', $orderId);
        return $this;
    }
}

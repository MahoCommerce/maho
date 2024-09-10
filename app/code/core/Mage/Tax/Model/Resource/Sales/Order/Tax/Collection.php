<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Order Tax Collection
 *
 * @category   Mage
 * @package    Mage_Tax
 *
 * @method Mage_Tax_Model_Sales_Order_Tax[] getItems()
 */
class Mage_Tax_Model_Resource_Sales_Order_Tax_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/sales_order_tax');
    }

    /**
     * Retrieve order tax collection by order identifier
     *
     * @param Varien_Object $order
     * @return $this
     */
    public function loadByOrder($order)
    {
        $orderId = $order->getId();
        $this->getSelect()
            ->where('main_table.order_id = ?', (int)$orderId)
            ->order('process');
        return $this->load();
    }
}

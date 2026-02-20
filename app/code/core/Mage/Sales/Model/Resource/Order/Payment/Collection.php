<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Sales_Model_Order_Payment getItemById(int $value)
 * @method Mage_Sales_Model_Order_Payment[] getItems()
 */
class Mage_Sales_Model_Resource_Order_Payment_Collection extends Mage_Sales_Model_Resource_Order_Collection_Abstract
{
    /**
     * @var string
     */
    protected $_eventPrefix    = 'sales_order_payment_collection';

    /**
     * @var string
     */
    protected $_eventObject    = 'order_payment_collection';

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_payment');
    }

    /**
     * Unserialize additional_information in each item
     */
    #[\Override]
    protected function _afterLoad()
    {
        foreach ($this->_items as $item) {
            $this->getResource()->unserializeFields($item);
        }
        return parent::_afterLoad();
    }
}

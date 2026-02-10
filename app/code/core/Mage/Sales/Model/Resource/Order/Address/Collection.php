<?php

declare(strict_types=1);

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
 * @method Mage_Sales_Model_Order_Address getItemById(int $value)
 * @method Mage_Sales_Model_Order_Address[] getItems()
 */
class Mage_Sales_Model_Resource_Order_Address_Collection extends Mage_Sales_Model_Resource_Order_Collection_Abstract
{
    /**
     * @var string
     */
    protected $_eventPrefix    = 'sales_order_address_collection';

    /**
     * @var string
     */
    protected $_eventObject    = 'order_address_collection';

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_address');
    }

    /**
     * Redeclare after load method for dispatch event
     *
     * @return $this
     */
    #[\Override]
    protected function _afterLoad()
    {
        parent::_afterLoad();

        Mage::dispatchEvent($this->_eventPrefix . '_load_after', [
            $this->_eventObject => $this,
        ]);

        return $this;
    }
}

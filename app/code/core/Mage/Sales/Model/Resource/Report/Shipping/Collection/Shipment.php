<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Sales report shipping collection
 *
 * @category   Mage
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Report_Shipping_Collection_Shipment extends Mage_Sales_Model_Resource_Report_Shipping_Collection_Order
{
    public function __construct()
    {
        $this->setModel('adminhtml/report_item');
        $this->_resource = Mage::getResourceModel('sales/report')->init('sales/shipping_aggregated');
        $this->setConnection($this->getResource()->getReadConnection());
    }
}

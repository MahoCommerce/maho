<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
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

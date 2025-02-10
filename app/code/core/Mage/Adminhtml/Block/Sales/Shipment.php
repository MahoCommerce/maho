<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml sales shipments block
 *
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Sales_Shipment extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'sales_shipment';
        $this->_headerText = Mage::helper('sales')->__('Shipments');
        parent::__construct();
        $this->_removeButton('add');
    }
}

<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Adminhtml_Billing_Agreement extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Initialize billing agreements grid container
     */
    public function __construct()
    {
        $this->_controller = 'adminhtml_billing_agreement';
        $this->_blockGroup = 'sales';
        $this->_headerText = Mage::helper('sales')->__('Billing Agreements');
        parent::__construct();
        $this->_removeButton('add');
    }
}

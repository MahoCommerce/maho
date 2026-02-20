<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Payment_Block_Adminhtml_Payment_Restriction extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'adminhtml_payment_restriction';
        $this->_blockGroup = 'payment';
        $this->_headerText = Mage::helper('payment')->__('Payment Restrictions');
        $this->_addButtonLabel = Mage::helper('payment')->__('Add New Restriction');
        parent::__construct();
    }
}

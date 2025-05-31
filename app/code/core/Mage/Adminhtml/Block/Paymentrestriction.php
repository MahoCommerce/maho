<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restrictions container block
 */
class Mage_Adminhtml_Block_Paymentrestriction extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_controller = 'paymentrestriction';
        $this->_headerText = Mage::helper('payment')->__('Payment Restrictions');
        $this->_addButtonLabel = Mage::helper('payment')->__('Add New Restriction');
        parent::__construct();
    }
}

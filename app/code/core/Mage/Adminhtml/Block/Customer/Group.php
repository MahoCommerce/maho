<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml customers group page content block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Customer_Group extends Mage_Adminhtml_Block_Widget_Grid_Container //Mage_Adminhtml_Block_Template
{
    /**
     * Modify header & button labels
     *
     */
    public function __construct()
    {
        $this->_controller = 'customer_group';
        $this->_headerText = Mage::helper('customer')->__('Customer Groups');
        $this->_addButtonLabel = Mage::helper('customer')->__('Add New Customer Group');
        parent::__construct();
    }
}

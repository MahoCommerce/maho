<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml sales order create sidebar viewed block
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Viewed extends Mage_Adminhtml_Block_Sales_Order_Create_Sidebar_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setId('sales_order_create_sidebar_viewed');
        $this->setDataId('viewed');
    }

    /**
     * Retrieve display block availability
     *
     * @return int|false
     */
    #[\Override]
    public function canDisplay()
    {
        return false;
    }

    /**
     * Retrieve availability removing items in block
     *
     * @return false
     */
    #[\Override]
    public function canRemoveItems()
    {
        return false;
    }

    /**
     * @return string
     */
    public function getHeaderText()
    {
        return Mage::helper('sales')->__('Recently Viewed');
    }
}

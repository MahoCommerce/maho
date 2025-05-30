<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Sales_Order_Address extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_controller = 'sales_order';
        $this->_mode       = 'address';
        parent::__construct();
        $this->_updateButton('save', 'label', Mage::helper('sales')->__('Save Order Address'));
        $this->_removeButton('delete');
    }

    /**
     * Retrieve text for header element depending on loaded page
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        $address = Mage::registry('order_address');
        $orderId = $address->getOrder()->getIncrementId();
        if ($address->getAddressType() == 'shipping') {
            $type = Mage::helper('sales')->__('Shipping');
        } else {
            $type = Mage::helper('sales')->__('Billing');
        }
        return Mage::helper('sales')->__('Edit Order %s %s Address', $orderId, $type);
    }

    /**
     * Back button url getter
     *
     * @return string
     */
    #[\Override]
    public function getBackUrl()
    {
        return $this->getUrl(
            '*/*/view',
            ['order_id' => Mage::registry('order_address')->getOrder()->getId()],
        );
    }
}

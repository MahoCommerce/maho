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

class Mage_Adminhtml_Block_Sales_Order_View_Tab_Info extends Mage_Adminhtml_Block_Sales_Order_Abstract implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    #[\Override]
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Retrieve source model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getSource()
    {
        return $this->getOrder();
    }

    /**
     * Retrieve order totals block settings
     *
     * @return array
     */
    #[\Override]
    public function getOrderTotalData()
    {
        return [
            'can_display_total_due'      => true,
            'can_display_total_paid'     => true,
            'can_display_total_refunded' => true,
        ];
    }

    #[\Override]
    public function getOrderInfoData()
    {
        return [
            'no_use_order_link' => true,
        ];
    }

    public function getTrackingHtml()
    {
        return $this->getChildHtml('order_tracking');
    }

    public function getItemsHtml()
    {
        return $this->getChildHtml('order_items');
    }

    /**
     * Retrieve giftmessage block html
     *
     * @deprecated after 1.4.2.0, use self::getGiftOptionsHtml() instead
     * @return string
     */
    public function getGiftmessageHtml()
    {
        return $this->getChildHtml('order_giftmessage');
    }

    /**
     * Retrieve gift options container block html
     *
     * @return string
     */
    public function getGiftOptionsHtml()
    {
        return $this->getChildHtml('gift_options');
    }

    public function getPaymentHtml()
    {
        return $this->getChildHtml('order_payment');
    }

    public function getViewUrl($orderId)
    {
        return $this->getUrl('*/*/*', ['order_id' => $orderId]);
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('sales')->__('Information');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('sales')->__('Information');
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Places the order of one quote through Mage_Sales_Service_Order::submit(), as a staff member: the shopper rules
 * do not apply, and the new order email is not sent.
 *
 * @deprecated since 27.1, use Mage_Sales_Service_Order::submit()
 */
class Mage_Sales_Model_Service_Quote
{
    /**
     * @var Mage_Sales_Model_Quote
     */
    protected $_quote;

    /**
     * @var Mage_Sales_Model_Convert_Quote
     */
    protected $_convertor;

    /**
     * Fields that the order gets before its save
     *
     * @var array
     */
    protected $_orderData = [];

    /**
     * @var Mage_Sales_Model_Order|null
     */
    protected $_order = null;

    /**
     * False keeps the cart active after its order is placed
     *
     * @var bool
     */
    protected $_shouldInactivateQuote = true;

    public function __construct(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote       = $quote;
        $this->_convertor   = Mage::getModel('sales/convert_quote');
    }

    /**
     * @return $this
     */
    public function setConvertor(Mage_Sales_Model_Convert_Quote $convertor)
    {
        $this->_convertor = $convertor;
        return $this;
    }

    /**
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote()
    {
        return $this->_quote;
    }

    /**
     * @return $this
     */
    public function setOrderData(array $data)
    {
        $this->_orderData = $data;
        return $this;
    }

    /**
     * Place the order of the quote. Unlike the method of the same name before the services, it refuses a quote that
     * has an order. It sends no email and does not dispatch checkout_submit_all_after: the caller does both.
     *
     * @return Mage_Sales_Model_Order
     * @throws Mage_Core_Exception
     */
    public function submitOrder()
    {
        if (static::class !== self::class) {
            // A subclass can add a check to _validate(), which submit() does not call
            $this->_validate();
        }
        $this->_order = $this->getOrderService()->submitWithoutNotice(
            $this->_quote,
            Mage_Checkout_Model_Actor::Staff,
            $this->_orderData,
            $this->_convertor,
        );
        if (!$this->_shouldInactivateQuote) {
            // submit() closes and saves the cart of each order
            $this->_quote->setIsActive(true)->save();
        }
        return $this->_order;
    }

    /**
     * Place the order of the quote when it has items. A quote without items is closed.
     */
    public function submitAll()
    {
        if (!$this->_quote->getAllVisibleItems()) {
            $this->_inactivateQuote();
            return;
        }
        $this->submitOrder();
    }

    /**
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        return $this->_order;
    }

    /**
     * @return $this
     */
    protected function _inactivateQuote()
    {
        if ($this->_shouldInactivateQuote) {
            $this->_quote->setIsActive(false);
        }
        return $this;
    }

    /**
     * Check that the quote can become an order, without placing it.
     *
     * @throws Mage_Core_Exception
     */
    public function validate(): self
    {
        return $this->_validate();
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _validate()
    {
        $this->getOrderService()->validateQuote($this->getQuote(), Mage_Checkout_Model_Actor::Staff);
        return $this;
    }

    private function getOrderService(): Mage_Sales_Service_Order
    {
        return Mage::getService('sales/order');
    }
}

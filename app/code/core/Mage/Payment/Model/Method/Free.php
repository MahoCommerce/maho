<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Payment
 */

class Mage_Payment_Model_Method_Free extends Mage_Payment_Model_Method_Abstract
{
    /**
     * XML Paths for configuration constants
     */
    public const XML_PATH_PAYMENT_FREE_ACTIVE = 'payment/free/active';
    public const XML_PATH_PAYMENT_FREE_ORDER_STATUS = 'payment/free/order_status';
    public const XML_PATH_PAYMENT_FREE_PAYMENT_ACTION = 'payment/free/payment_action';

    /**
     * Payment Method features
     * @var bool
     */
    #[\Override]
    protected $_canAuthorize = true;

    /**
     * Payment code name
     *
     * @var string
     */
    #[\Override]
    protected $_code = 'free';

    /**
     * Check whether method is available
     *
     * @param Mage_Sales_Model_Quote|null $quote
     * @return bool
     */
    #[\Override]
    public function isAvailable($quote = null)
    {
        return parent::isAvailable($quote) && !empty($quote)
            && Mage::app()->getStore()->roundPrice($quote->getGrandTotal()) == 0;
    }

    /**
     * Refuse an order or a quote that has something to pay.
     *
     * isAvailable() runs only when the payment data is imported. The order can be placed later
     * without a new import, after the cart total went up.
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function validate()
    {
        parent::validate();

        $paymentInfo = $this->getInfoInstance();
        $grandTotal = $paymentInfo instanceof Mage_Sales_Model_Order_Payment
            ? $paymentInfo->getOrder()->getGrandTotal()
            : $paymentInfo->getQuote()->getGrandTotal();

        if (Mage::app()->getStore()->roundPrice((float) $grandTotal) != 0) {
            Mage::throwException(Mage::helper('payment')->__('The requested Payment Method is not available.'));
        }

        return $this;
    }

    /**
     * Get config payment action, do nothing if status is pending
     *
     * @return string|null
     */
    #[\Override]
    public function getConfigPaymentAction()
    {
        return $this->getConfigData('order_status') == 'pending' ? null : parent::getConfigPaymentAction();
    }
}

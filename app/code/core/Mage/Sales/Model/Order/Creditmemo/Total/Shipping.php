<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Creditmemo_Total_Shipping extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    /**
     * @return $this
     * @throws Mage_Core_Exception
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $order = $creditmemo->getOrder();
        $allowedAmount          = $order->getShippingAmount() - $order->getShippingRefunded();
        $baseAllowedAmount      = $order->getBaseShippingAmount() - $order->getBaseShippingRefunded();

        $shipping               = $order->getShippingAmount();
        $baseShipping           = $order->getBaseShippingAmount();
        $shippingInclTax        = $order->getShippingInclTax();
        $baseShippingInclTax    = $order->getBaseShippingInclTax();

        $isShippingInclTax = Mage::getSingleton('tax/config')->displaySalesShippingInclTax($order->getStoreId());

        // What is left of the shipping incl. tax: the shipping and its tax left, and the share of the shipping
        // hidden tax, which no column records as refunded.
        $allowedInclTax = null;
        $baseAllowedInclTax = null;
        if ($baseShipping != 0) {
            $allowedInclTax = Mage::app()->getStore()->roundPrice(
                $allowedAmount + $order->getShippingTaxAmount() - $order->getShippingTaxRefunded()
                + $this->_getShippingShare($creditmemo, (float) $order->getShippingHiddenTaxAmount(), (float) $baseAllowedAmount),
            );
            $baseAllowedInclTax = Mage::app()->getStore()->roundPrice(
                $baseAllowedAmount + $order->getBaseShippingTaxAmount() - $order->getBaseShippingTaxRefunded()
                + $this->_getShippingShare($creditmemo, (float) $order->getBaseShippingHiddenTaxAmount(), (float) $baseAllowedAmount),
            );
        }

        /**
         * Check if shipping amount was specified (from invoice or another source).
         * Using has magic method to allow setting 0 as shipping amount.
         */
        if ($creditmemo->hasBaseShippingAmount()) {
            $baseShippingAmount = Mage::app()->getStore()->roundPrice($creditmemo->getBaseShippingAmount());
            if ($isShippingInclTax && $baseAllowedInclTax !== null
                && abs($baseShippingAmount - $baseAllowedInclTax) < 0.0001
            ) {
                // Converting the whole of what is left at the rate of the order could round past it.
                $shippingInclTax = $allowedInclTax;
                $baseShippingInclTax = $baseAllowedInclTax;
                $baseShippingAmount = $baseAllowedAmount;
            } elseif ($isShippingInclTax && $baseShippingInclTax != 0) {
                $part = $baseShippingAmount / $baseShippingInclTax;
                $shippingInclTax    = Mage::app()->getStore()->roundPrice($shippingInclTax * $part);
                $baseShippingInclTax = $baseShippingAmount;
                $baseShippingAmount = Mage::app()->getStore()->roundPrice($baseShipping * $part);
            }
            /*
             * Rounded allowed shipping refund amount is the highest acceptable shipping refund amount.
             * Shipping refund amount shouldn't cause errors, if it doesn't exceed that limit.
             * Note: ($x < $y + 0.0001) means ($x <= $y) for floats
             */
            if ($baseShippingAmount < Mage::app()->getStore()->roundPrice($baseAllowedAmount) + 0.0001) {
                /*
                 * Shipping refund amount should be equated to allowed refund amount,
                 * if it exceeds that limit.
                 * Note: ($x > $y - 0.0001) means ($x >= $y) for floats
                 */
                if ($baseShippingAmount > $baseAllowedAmount - 0.0001) {
                    $shipping     = $allowedAmount;
                    $baseShipping = $baseAllowedAmount;
                } else {
                    if ($baseShipping != 0) {
                        $shipping = $shipping * $baseShippingAmount / $baseShipping;
                    }
                    $shipping     = Mage::app()->getStore()->roundPrice($shipping);
                    $baseShipping = $baseShippingAmount;
                }
            } else {
                $baseAllowedAmount = $order->getBaseCurrency()->format($baseAllowedAmount, null, false);
                Mage::throwException(
                    Mage::helper('sales')->__('Maximum shipping amount allowed to refund is: %s', $baseAllowedAmount),
                );
            }
        } else {
            if ($baseAllowedInclTax !== null) {
                $shippingInclTax = $allowedInclTax;
                $baseShippingInclTax = $baseAllowedInclTax;
            }
            $shipping           = $allowedAmount;
            $baseShipping       = $baseAllowedAmount;
        }

        $creditmemo->setShippingAmount($shipping);
        $creditmemo->setBaseShippingAmount($baseShipping);
        $creditmemo->setShippingInclTax($shippingInclTax);
        $creditmemo->setBaseShippingInclTax($baseShippingInclTax);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $shipping);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseShipping);
        return $this;
    }
}

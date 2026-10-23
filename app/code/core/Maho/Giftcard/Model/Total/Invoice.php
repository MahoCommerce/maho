<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Model_Total_Invoice extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();

        $baseOrderAmount = abs((float) $order->getBaseGiftcardAmount());
        $orderAmount = abs((float) $order->getGiftcardAmount());
        if ($baseOrderAmount <= 0) {
            return $this;
        }

        $invoiced = Mage::helper('giftcard')->getInvoicedAmounts($order);
        $baseRemaining = $baseOrderAmount - $invoiced['base'];
        $remaining = $orderAmount - $invoiced['amount'];
        if ($baseRemaining <= 0) {
            return $this;
        }

        if ($invoice->isLast()) {
            $baseAmount = $baseRemaining;
            $amount = $remaining;
        } else {
            // The credit memo collector refunds the gift card with the same ratio
            $baseOrderTotal = (float) $order->getBaseSubtotal()
                + (float) $order->getBaseShippingAmount()
                + (float) $order->getBaseTaxAmount();
            $baseInvoiceTotal = (float) $invoice->getBaseSubtotal()
                + (float) $invoice->getBaseShippingAmount()
                + (float) $invoice->getBaseTaxAmount();
            $ratio = $baseOrderTotal > 0 ? min(1.0, $baseInvoiceTotal / $baseOrderTotal) : 1.0;

            $store = $order->getStore();
            $baseAmount = min($baseRemaining, $store->roundPrice($baseOrderAmount * $ratio));
            $amount = min($remaining, $store->roundPrice($orderAmount * $ratio));
        }

        $baseAmount = min($baseAmount, (float) $invoice->getBaseGrandTotal());
        $amount = min($amount, (float) $invoice->getGrandTotal());

        $invoice->setBaseGiftcardAmount($baseAmount);
        $invoice->setGiftcardAmount($amount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() - $baseAmount);
        $invoice->setGrandTotal($invoice->getGrandTotal() - $amount);

        return $this;
    }
}

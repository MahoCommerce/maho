<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Invoice_Total_Shipping extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    /**
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $invoice->setShippingAmount(0);
        $invoice->setBaseShippingAmount(0);
        $orderShippingAmount        = $invoice->getOrder()->getShippingAmount();
        $baseOrderShippingAmount    = $invoice->getOrder()->getBaseShippingAmount();
        $shippingInclTax            = $invoice->getOrder()->getShippingInclTax();
        $baseShippingInclTax        = $invoice->getOrder()->getBaseShippingInclTax();
        if ($orderShippingAmount) {
            /**
             * Check shipping amount in previous invoices
             */
            foreach ($invoice->getOrder()->getInvoiceCollection() as $previusInvoice) {
                if ($previusInvoice->getShippingAmount() && !$previusInvoice->isCanceled()) {
                    return $this;
                }
            }
            $invoice->setShippingAmount($orderShippingAmount);
            $invoice->setBaseShippingAmount($baseOrderShippingAmount);
            $invoice->setShippingInclTax($shippingInclTax);
            $invoice->setBaseShippingInclTax($baseShippingInclTax);

            $invoice->setGrandTotal($invoice->getGrandTotal() + $orderShippingAmount);
            $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseOrderShippingAmount);
        }
        return $this;
    }
}

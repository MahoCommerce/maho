<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Creditmemo_Total_Tax extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    /**
     * Collects the total tax for the credit memo
     *
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $shippingTaxAmount     = 0;
        $baseShippingTaxAmount = 0;
        $totalTax              = 0;
        $baseTotalTax          = 0;
        $totalHiddenTax        = 0;
        $baseTotalHiddenTax    = 0;

        $order = $creditmemo->getOrder();

        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem->isDummy()) {
                continue;
            }
            $orderItemTax     = $orderItem->getTaxInvoiced();
            $baseOrderItemTax = $orderItem->getBaseTaxInvoiced();
            $orderItemHiddenTax = $orderItem->getHiddenTaxInvoiced();
            $baseOrderItemHiddenTax = $orderItem->getBaseHiddenTaxInvoiced();
            $orderItemQty     = $orderItem->getQtyInvoiced();

            if (($orderItemTax || $orderItemHiddenTax) && $orderItemQty) {
                /**
                 * Check item tax amount
                 */
                $tax            = $orderItemTax - $orderItem->getTaxRefunded();
                $baseTax        = $baseOrderItemTax - $orderItem->getBaseTaxRefunded();
                $hiddenTax      = $orderItemHiddenTax - $orderItem->getHiddenTaxRefunded();
                $baseHiddenTax  = $baseOrderItemHiddenTax - $orderItem->getBaseHiddenTaxRefunded();
                if (!$item->isLast()) {
                    $availableQty  = $orderItemQty - $orderItem->getQtyRefunded();
                    $tax           = $creditmemo->roundPrice($tax / $availableQty * $item->getQty());
                    $baseTax       = $creditmemo->roundPrice($baseTax / $availableQty * $item->getQty(), 'base');
                    $hiddenTax     = $creditmemo->roundPrice($hiddenTax / $availableQty * $item->getQty());
                    $baseHiddenTax = $creditmemo->roundPrice($baseHiddenTax / $availableQty * $item->getQty(), 'base');
                }

                $item->setTaxAmount($tax);
                $item->setBaseTaxAmount($baseTax);
                $item->setHiddenTaxAmount($hiddenTax);
                $item->setBaseHiddenTaxAmount($baseHiddenTax);

                $totalTax += $tax;
                $baseTotalTax += $baseTax;
                $totalHiddenTax += $hiddenTax;
                $baseTotalHiddenTax += $baseHiddenTax;
            }
        }

        // A credit memo of an invoice refunds the shipping tax that invoice charged, which is the whole shipping
        // tax of the order when the invoice charged the shipping, and none otherwise.
        $invoice = $creditmemo->getInvoice();
        $source = $invoice ?: $order;
        $baseShippingAmount = (float) $creditmemo->getBaseShippingAmount();
        $baseShippingLeft = (float) $order->getBaseShippingAmount() - (float) $order->getBaseShippingRefunded();

        if ($baseShippingAmount > 0) {
            if (!$invoice && $baseShippingAmount > $baseShippingLeft - 0.0001) {
                $shippingTaxAmount     = $order->getShippingTaxAmount() - $order->getShippingTaxRefunded();
                $baseShippingTaxAmount = $order->getBaseShippingTaxAmount() - $order->getBaseShippingTaxRefunded();
            } else {
                $shippingTaxAmount     = $this->_getShippingShare($creditmemo, (float) $source->getShippingTaxAmount(), $baseShippingAmount);
                $baseShippingTaxAmount = $this->_getShippingShare($creditmemo, (float) $source->getBaseShippingTaxAmount(), $baseShippingAmount);
            }
            $totalTax           += $shippingTaxAmount;
            $baseTotalTax       += $baseShippingTaxAmount;
            // No column records the refunded shipping hidden tax, so take a share counted on the shipping refunded.
            $totalHiddenTax     += $this->_getShippingShare($creditmemo, (float) $source->getShippingHiddenTaxAmount(), $baseShippingAmount);
            $baseTotalHiddenTax += $this->_getShippingShare($creditmemo, (float) $source->getBaseShippingHiddenTaxAmount(), $baseShippingAmount);
        }

        $allowedTax = $order->getTaxInvoiced() - $order->getTaxRefunded() - $creditmemo->getTaxAmount();
        $allowedBaseTax = $order->getBaseTaxInvoiced() - $order->getBaseTaxRefunded()
                - $creditmemo->getBaseTaxAmount();
        // The invoiced and refunded hidden tax of the order both include the shipping hidden tax.
        $allowedHiddenTax = $order->getHiddenTaxInvoiced() - $order->getHiddenTaxRefunded();
        $allowedBaseHiddenTax = $order->getBaseHiddenTaxInvoiced() - $order->getBaseHiddenTaxRefunded();

        $totalTax = min($allowedTax, $totalTax);
        $baseTotalTax = min($allowedBaseTax, $baseTotalTax);
        $totalHiddenTax = min($allowedHiddenTax, $totalHiddenTax);
        $baseTotalHiddenTax = min($allowedBaseHiddenTax, $baseTotalHiddenTax);

        $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $totalTax);
        $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() +  $baseTotalTax);
        $creditmemo->setHiddenTaxAmount($totalHiddenTax);
        $creditmemo->setBaseHiddenTaxAmount($baseTotalHiddenTax);

        $creditmemo->setShippingTaxAmount($shippingTaxAmount);
        $creditmemo->setBaseShippingTaxAmount($baseShippingTaxAmount);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalTax + $totalHiddenTax);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTotalTax + $baseTotalHiddenTax);
        return $this;
    }
}

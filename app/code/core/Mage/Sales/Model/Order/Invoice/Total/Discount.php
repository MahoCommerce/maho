<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Invoice_Total_Discount extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    /**
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $invoice->setDiscountAmount(0);
        $invoice->setBaseDiscountAmount(0);

        $totalDiscountAmount     = 0;
        $baseTotalDiscountAmount = 0;

        $order = $invoice->getOrder();
        $addShippingDiscount = true;
        $baseShippingDiscount = (float) $order->getBaseShippingDiscountAmount();
        $checkLegacyShippingDiscount = !$order->getShippingAmount() && (float) $order->getBaseShippingAmount() > 0.0;
        foreach ($order->getInvoiceCollection() as $previousInvoice) {
            if ($previousInvoice->isCanceled()) {
                continue;
            }
            if ($previousInvoice->getShippingAmount() || $previousInvoice->getBaseShippingAmount()) {
                $addShippingDiscount = false;
                break;
            }
            // Older invoices could apply the base discount while omitting the shipping charge.
            if ($checkLegacyShippingDiscount && $baseShippingDiscount > 0.0
                && $this->hasLegacyBaseShippingDiscount($previousInvoice, $baseShippingDiscount)
            ) {
                $baseShippingDiscount = 0.0;
            }
        }

        if ($addShippingDiscount) {
            $totalDiscountAmount     = $totalDiscountAmount + $order->getShippingDiscountAmount();
            $baseTotalDiscountAmount = $baseTotalDiscountAmount + $baseShippingDiscount;
        }

        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem->isDummy()) {
                continue;
            }

            $orderItemDiscount      = (float) $orderItem->getDiscountAmount();
            $baseOrderItemDiscount  = (float) $orderItem->getBaseDiscountAmount();
            $orderItemQty       = $orderItem->getQtyOrdered();

            if ($orderItemQty && ($orderItemDiscount || $baseOrderItemDiscount)) {
                /**
                 * Resolve rounding problems
                 *
                 * We don't want to include the weee discount amount as the right amount
                 * is added when calculating the taxes.
                 *
                 * Also, the subtotal is without weee
                 */

                $discount = $orderItemDiscount - $orderItem->getDiscountInvoiced();
                $baseDiscount = $baseOrderItemDiscount - $orderItem->getBaseDiscountInvoiced();

                if (!$item->isLast()) {
                    $activeQty = $orderItemQty - $orderItem->getQtyInvoiced();
                    $discount = $invoice->roundPrice($discount / $activeQty * $item->getQty(), 'regular', true);
                    $baseDiscount = $invoice->roundPrice($baseDiscount / $activeQty * $item->getQty(), 'base', true);
                }

                $item->setDiscountAmount($discount);
                $item->setBaseDiscountAmount($baseDiscount);

                $totalDiscountAmount += $discount;
                $baseTotalDiscountAmount += $baseDiscount;
            }
        }

        $invoice->setDiscountAmount(-$totalDiscountAmount);
        $invoice->setBaseDiscountAmount(-$baseTotalDiscountAmount);

        $invoice->setGrandTotal($invoice->getGrandTotal() - $totalDiscountAmount);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() - $baseTotalDiscountAmount);
        return $this;
    }

    private function hasLegacyBaseShippingDiscount(Mage_Sales_Model_Order_Invoice $invoice, float $baseShippingDiscount): bool
    {
        $items = $invoice->getAllItems();
        if ($items === []) {
            return false;
        }
        $baseItemDiscounts = array_sum(array_map(
            static fn(Mage_Sales_Model_Order_Invoice_Item $item): float => (float) $item->getBaseDiscountAmount(),
            $items,
        ));
        $remainder = round(-(float) $invoice->getBaseDiscountAmount() - $baseItemDiscounts, 4);
        return $remainder > 0.0 && $remainder === round($baseShippingDiscount, 4);
    }
}

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

        $order = $invoice->getOrder();
        $shippingDiscount = (float) $order->getShippingDiscountAmount();
        $baseShippingDiscount = (float) $order->getBaseShippingDiscountAmount();
        $checkBaseShippingDiscount = !$order->getShippingAmount() && (float) $order->getBaseShippingAmount() > 0.0;
        $baseShippingInvoicedWithoutDiscount = false;
        foreach ($order->getInvoiceCollection() as $previousInvoice) {
            if ($previousInvoice->isCanceled()) {
                continue;
            }
            $hasShipping = $previousInvoice->getShippingAmount() || $previousInvoice->getBaseShippingAmount();
            // Base-only shipping and its discount can be on different invoices after an upgrade.
            $discountOutsideItems = $checkBaseShippingDiscount && $baseShippingDiscount > 0.0
                ? $this->getBaseDiscountOutsideItems($previousInvoice)
                : null;
            if ($hasShipping) {
                $shippingDiscount = 0.0;
                if ($discountOutsideItems !== 0.0) {
                    $baseShippingDiscount = 0.0;
                    break;
                }
                $baseShippingInvoicedWithoutDiscount = true;
            } elseif ($discountOutsideItems > 0.0
                && $discountOutsideItems === round($baseShippingDiscount, 4)
            ) {
                $baseShippingDiscount = 0.0;
            }
        }

        $invoice->setData('has_remaining_base_shipping_discount', $baseShippingInvoicedWithoutDiscount && $baseShippingDiscount > 0.0);
        $totalDiscountAmount = $shippingDiscount;
        $baseTotalDiscountAmount = $baseShippingDiscount;

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
}

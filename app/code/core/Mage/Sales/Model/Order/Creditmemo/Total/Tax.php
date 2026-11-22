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
        $signedTax = (float) $order->getTaxAmount() < (float) $order->getShippingTaxAmount();
        $signedBaseTax = (float) $order->getBaseTaxAmount() < (float) $order->getBaseShippingTaxAmount();
        if ($signedTax || $signedBaseTax) {
            // A signed item tax can reach zero before all invoice quantities have been returned.
            $itemInvoice = $invoice ?: $this->getWholeItemInvoice($creditmemo, $order);
            [$taxLeft, $baseTaxLeft, $last] = $this->getSignedItemTaxLeft($creditmemo, $itemInvoice);
            if ($signedTax) {
                $allowedTax = $taxLeft + $shippingTaxAmount - $creditmemo->getTaxAmount();
                $totalTax = $last ? $allowedTax : $totalTax;
            }
            if ($signedBaseTax) {
                $allowedBaseTax = $baseTaxLeft + $baseShippingTaxAmount - $creditmemo->getBaseTaxAmount();
                $baseTotalTax = $last ? $allowedBaseTax : $baseTotalTax;
            }
        }
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

    /** @return array{float, float, bool} */
    private function getSignedItemTaxLeft(Mage_Sales_Model_Order_Creditmemo $creditmemo, ?Mage_Sales_Model_Order_Invoice $invoice): array
    {
        $quantities = [];
        $order = $creditmemo->getOrder();
        if ($invoice) {
            foreach ($invoice->getAllItems() as $item) {
                if (!$item->getOrderItem()->isDummy()) {
                    $quantities[$item->getOrderItemId()] = (float) $item->getQty();
                }
            }
            $tax = $invoice->getTaxAmount() - $invoice->getShippingTaxAmount();
            $baseTax = $invoice->getBaseTaxAmount() - $invoice->getBaseShippingTaxAmount();
            $previous = Mage::getResourceModel('sales/order_creditmemo_collection')->setOrderFilter($order);
            foreach ($previous as $memo) {
                if ($memo->getState() == Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED || $memo->getId() == $creditmemo->getId()) {
                    continue;
                }
                $invoiceId = $memo->getInvoiceId();
                if (!$invoiceId) {
                    $itemTax = $memo->getTaxAmount() - $memo->getShippingTaxAmount();
                    $baseItemTax = $memo->getBaseTaxAmount() - $memo->getBaseShippingTaxAmount();
                    if ($itemTax == 0 && $baseItemTax == 0 && !array_any($memo->getAllItems(), static fn($item): bool => $item->getQty() > 0)) {
                        continue;
                    }
                    $memo->setOrder($order);
                    $source = $this->getWholeItemInvoice($memo, $order);
                    // An older refund may have charged another invoice's outstanding tax.
                    if (!$source || round($source->getTaxAmount() - $source->getShippingTaxAmount() - $itemTax, 4) != 0
                        || round($source->getBaseTaxAmount() - $source->getBaseShippingTaxAmount() - $baseItemTax, 4) != 0) {
                        return $this->getSignedItemTaxLeft($creditmemo, null);
                    }
                    $invoiceId = $source->getId();
                }
                if ($invoiceId != $invoice->getId()) {
                    continue;
                }
                $tax -= $memo->getTaxAmount() - $memo->getShippingTaxAmount();
                $baseTax -= $memo->getBaseTaxAmount() - $memo->getBaseShippingTaxAmount();
                foreach ($memo->getAllItems() as $item) {
                    if (isset($quantities[$item->getOrderItemId()])) {
                        $quantities[$item->getOrderItemId()] -= $item->getQty();
                    }
                }
            }
        } else {
            $tax = $order->getTaxInvoiced() - $order->getTaxRefunded() + $order->getShippingTaxRefunded();
            $baseTax = $order->getBaseTaxInvoiced() - $order->getBaseTaxRefunded() + $order->getBaseShippingTaxRefunded();
            foreach ($order->getInvoiceCollection() as $document) {
                if (!$document->isCanceled()) {
                    $tax -= $document->getShippingTaxAmount();
                    $baseTax -= $document->getBaseShippingTaxAmount();
                }
            }
            foreach ($order->getAllItems() as $item) {
                if (!$item->isDummy()) {
                    $quantities[$item->getId()] = $item->getQtyToRefund();
                }
            }
        }
        $hasItems = false;
        foreach ($creditmemo->getAllItems() as $item) {
            if ($item->getQty() > 0 && isset($quantities[$item->getOrderItemId()])) {
                $quantities[$item->getOrderItemId()] -= $item->getQty();
                $hasItems = true;
            }
        }
        return $hasItems ? [$tax, $baseTax, round(max($quantities), 4) <= 0.0] : [0.0, 0.0, true];
    }

    private function getWholeItemInvoice(Mage_Sales_Model_Order_Creditmemo $memo, Mage_Sales_Model_Order $order): ?Mage_Sales_Model_Order_Invoice
    {
        // Keep an unlinked refund with an adjustment on the order balance.
        if ($memo->getBaseAdjustmentPositive() || $memo->getBaseAdjustmentNegative()
            || $memo->getAdjustmentPositive() || $memo->getAdjustmentNegative()) {
            return null;
        }

        $quantities = $this->getItemQuantities($memo);
        if (!$quantities) {
            return null;
        }
        $source = null;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->isCanceled()) {
                continue;
            }
            $invoiced = $this->getItemQuantities($invoice);
            if (!array_intersect_key($quantities, $invoiced)) {
                continue;
            }
            if ($source || $invoice->getState() !== Mage_Sales_Model_Order_Invoice::STATE_PAID || $quantities != $invoiced) {
                return null;
            }
            $source = $invoice;
        }
        return $source;
    }

    /** @return array<int, float> */
    private function getItemQuantities(Mage_Sales_Model_Order_Creditmemo|Mage_Sales_Model_Order_Invoice $document): array
    {
        $quantities = [];
        foreach ($document->getAllItems() as $item) {
            if ($item->getQty() > 0 && !$item->getOrderItem()?->isDummy()) {
                $id = $item->getOrderItemId();
                $quantities[$id] = ($quantities[$id] ?? 0.0) + $item->getQty();
            }
        }
        return $quantities;
    }
}

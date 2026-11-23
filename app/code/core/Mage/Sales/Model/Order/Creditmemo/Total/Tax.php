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
        $invoice = $creditmemo->getInvoice();
        $signedTax = (float) $order->getTaxAmount() < (float) $order->getShippingTaxAmount();
        $signedBaseTax = (float) $order->getBaseTaxAmount() < (float) $order->getBaseShippingTaxAmount();
        if ($invoice && (!$signedTax || !$signedBaseTax)) {
            foreach ($invoice->getAllItems() as $line) {
                $signedTax = $signedTax || (float) $line->getTaxAmount() < 0.0;
                $signedBaseTax = $signedBaseTax || (float) $line->getBaseTaxAmount() < 0.0;
            }
        }
        $invoiceTaxes = [];
        if ($signedTax || $signedBaseTax) {
            $itemInvoice = $invoice ?: $this->getWholeItemInvoice($creditmemo, $order);
            [$taxLeft, $baseTaxLeft, $positiveTax, $basePositiveTax, $last, $invoiceTaxes] = $this->getSignedItemTaxLeft($creditmemo, $itemInvoice);
        }

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

            if ($orderItemQty && ($orderItemTax || $baseOrderItemTax || $orderItemHiddenTax || $baseOrderItemHiddenTax)) {
                /**
                 * Check item tax amount
                 */
                $tax            = $orderItemTax - $orderItem->getTaxRefunded();
                $baseTax        = $baseOrderItemTax - $orderItem->getBaseTaxRefunded();
                $hiddenTax      = $orderItemHiddenTax - $orderItem->getHiddenTaxRefunded();
                $baseHiddenTax  = $baseOrderItemHiddenTax - $orderItem->getBaseHiddenTaxRefunded();
                $source = $invoiceTaxes[$item->getOrderItemId()] ?? null;
                $availableQty = $orderItemQty - $orderItem->getQtyRefunded();
                $tax = $this->getLineTax($item, $tax, $availableQty, $signedTax ? $source : null, 'regular');
                $baseTax = $this->getLineTax($item, $baseTax, $availableQty, $signedBaseTax ? $source : null, 'base');
                if (!$item->isLast()) {
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
        $taxRefund = min($allowedTax, $totalTax);
        $baseTaxRefund = min($allowedBaseTax, $baseTotalTax);
        if ($signedTax || $signedBaseTax) {
            // A signed item tax can reach zero before all invoice quantities have been returned.
            if ($signedTax) {
                $allowedTax = $taxLeft + $shippingTaxAmount - $creditmemo->getTaxAmount();
                $taxRefund = $this->getSignedRefundTax($totalTax, $allowedTax, $shippingTaxAmount, $positiveTax, $last, array_column($invoiceTaxes, 'regular'));
            }
            if ($signedBaseTax) {
                $allowedBaseTax = $baseTaxLeft + $baseShippingTaxAmount - $creditmemo->getBaseTaxAmount();
                $baseTaxRefund = $this->getSignedRefundTax($baseTotalTax, $allowedBaseTax, $baseShippingTaxAmount, $basePositiveTax, $last, array_column($invoiceTaxes, 'base'));
            }
        }
        // The invoiced and refunded hidden tax of the order both include the shipping hidden tax.
        $allowedHiddenTax = $order->getHiddenTaxInvoiced() - $order->getHiddenTaxRefunded();
        $allowedBaseHiddenTax = $order->getBaseHiddenTaxInvoiced() - $order->getBaseHiddenTaxRefunded();

        $totalHiddenTax = min($allowedHiddenTax, $totalHiddenTax);
        $baseTotalHiddenTax = min($allowedBaseHiddenTax, $baseTotalHiddenTax);

        $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $taxRefund);
        $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseTaxRefund);
        $creditmemo->setHiddenTaxAmount($totalHiddenTax);
        $creditmemo->setBaseHiddenTaxAmount($baseTotalHiddenTax);

        $creditmemo->setShippingTaxAmount($shippingTaxAmount);
        $creditmemo->setBaseShippingTaxAmount($baseShippingTaxAmount);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $taxRefund + $totalHiddenTax);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTaxRefund + $baseTotalHiddenTax);
        return $this;
    }

    /** @param array<?float> $invoiceTaxes */
    private function getSignedRefundTax(float $tax, float $allowed, float $shippingTax, ?float $positiveItemTax, bool $last, array $invoiceTaxes): float
    {
        if ($last) {
            return $allowed;
        }
        // Each returned line already uses its own invoice balance. Another line or fee may carry the opposite sign.
        if ($invoiceTaxes && !in_array(null, $invoiceTaxes, true)) {
            return $tax;
        }
        // Shipping tax can make the memo positive even when its returned item has a negative tax.
        if ($tax < $shippingTax) {
            return max($tax, min($allowed - ($positiveItemTax ?? 0.0), $shippingTax));
        }
        if ($positiveItemTax !== null) {
            return min($tax, $shippingTax + $positiveItemTax);
        }
        return min($tax, $allowed);
    }

    /** @param array{qty: float, regular: ?float, base: ?float}|null $source */
    private function getLineTax(Mage_Sales_Model_Order_Creditmemo_Item $item, float $tax, float $availableQty, ?array $source, string $type): float
    {
        $last = $item->isLast();
        $qty = (float) $item->getQty();
        if (isset($source[$type])) {
            $tax = $source[$type];
            $availableQty = $source['qty'];
            $last = $qty === $availableQty;
        }
        return $last ? $tax : $item->getCreditmemo()->roundPrice($tax / $availableQty * $qty, $type);
    }

    /** @return array{float, float, ?float, ?float, bool, array<int, array{qty: float, regular: ?float, base: ?float}>} */
    private function getSignedItemTaxLeft(Mage_Sales_Model_Order_Creditmemo $creditmemo, ?Mage_Sales_Model_Order_Invoice $invoice): array
    {
        $quantities = [];
        $lineTaxes = $baseLineTaxes = [];
        $order = $creditmemo->getOrder();
        if ($invoice) {
            foreach ($invoice->getAllItems() as $item) {
                if (!$item->getOrderItem()->isDummy()) {
                    $id = $item->getOrderItemId();
                    $quantities[$id] = (float) $item->getQty();
                    $lineTaxes[$id] = [$item->getTaxAmount(), 0.0];
                    $baseLineTaxes[$id] = [$item->getBaseTaxAmount(), 0.0];
                }
            }
            $tax = $invoice->getTaxAmount() - $invoice->getShippingTaxAmount();
            $baseTax = $invoice->getBaseTaxAmount() - $invoice->getBaseShippingTaxAmount();
            $previous = Mage::getResourceModel('sales/order_creditmemo_collection')->setOrderFilter($order);
            $priorMemos = [];
            $invoicesWithRefundedItemTax = [];
            foreach ($previous as $memo) {
                if ($memo->getState() == Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED || $memo->getId() == $creditmemo->getId()) {
                    continue;
                }
                $priorMemos[] = $memo;
                $invoiceId = $memo->getInvoiceId();
                if ($invoiceId && ((float) $memo->getTaxAmount() !== (float) $memo->getShippingTaxAmount()
                    || (float) $memo->getBaseTaxAmount() !== (float) $memo->getBaseShippingTaxAmount())
                ) {
                    $invoicesWithRefundedItemTax[$invoiceId] = true;
                }
            }
            foreach ($priorMemos as $memo) {
                $invoiceId = $memo->getInvoiceId();
                if (!$invoiceId) {
                    $itemTax = $memo->getTaxAmount() - $memo->getShippingTaxAmount();
                    $baseItemTax = $memo->getBaseTaxAmount() - $memo->getBaseShippingTaxAmount();
                    if ($itemTax == 0 && $baseItemTax == 0 && !array_any($memo->getAllItems(), static fn($item): bool => $item->getQty() > 0)) {
                        continue;
                    }
                    $memo->setOrder($order);
                    $source = $this->getWholeItemInvoice($memo, $order);
                    // The original invoice tax proves attribution only while no linked refund has consumed it.
                    if (!$source || isset($invoicesWithRefundedItemTax[$source->getId()])
                        || round($source->getTaxAmount() - $source->getShippingTaxAmount() - $itemTax, 4) != 0
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
                    $id = $item->getOrderItemId();
                    if (isset($quantities[$id])) {
                        $quantities[$id] -= $item->getQty();
                        $lineTaxes[$id][1] += $lineTaxes[$id][0] < 0 ? min(0.0, (float) $item->getTaxAmount()) : max(0.0, (float) $item->getTaxAmount());
                        $baseLineTaxes[$id][1] += $baseLineTaxes[$id][0] < 0 ? min(0.0, (float) $item->getBaseTaxAmount()) : max(0.0, (float) $item->getBaseTaxAmount());
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
                    $id = $item->getId();
                    $quantities[$id] = $item->getQtyToRefund();
                    $lineTaxes[$id] = [(float) $item->getTaxInvoiced(), (float) $item->getTaxRefunded()];
                    $baseLineTaxes[$id] = [(float) $item->getBaseTaxInvoiced(), (float) $item->getBaseTaxRefunded()];
                }
            }
        }
        $positiveTax = $this->getPositiveLineTaxLeft($lineTaxes, $quantities);
        $basePositiveTax = $this->getPositiveLineTaxLeft($baseLineTaxes, $quantities);
        $hasItems = false;
        $invoiceTaxes = [];
        foreach ($creditmemo->getAllItems() as $item) {
            $id = $item->getOrderItemId();
            if ($invoice && $creditmemo->getInvoice() && $item->getQty() > 0 && !$item->getOrderItem()->isDummy()) {
                $invoiceTaxes[$id] = ['qty' => $quantities[$id] ?? 0.0, 'regular' => null, 'base' => null];
                if ($item->getQty() <= $invoiceTaxes[$id]['qty']) {
                    if ($positiveTax !== null) {
                        $invoiceTaxes[$id]['regular'] = $this->getInvoiceLineBalance($lineTaxes[$id], (float) $item->getOrderItem()->getTaxInvoiced());
                    }
                    if ($basePositiveTax !== null) {
                        $invoiceTaxes[$id]['base'] = $this->getInvoiceLineBalance($baseLineTaxes[$id], (float) $item->getOrderItem()->getBaseTaxInvoiced());
                    }
                }
            }
            if ($item->getQty() > 0 && isset($quantities[$id])) {
                $quantities[$id] -= $item->getQty();
                $hasItems = true;
            }
        }
        return $hasItems ? [$tax, $baseTax, $positiveTax, $basePositiveTax, round(max($quantities), 4) <= 0.0, $invoiceTaxes] : [0.0, 0.0, 0.0, 0.0, true, []];
    }

    /** @param array{?float, float} $line */
    private function getInvoiceLineBalance(array $line, float $orderTax): ?float
    {
        [$invoiced, $refunded] = $line;
        if ($invoiced === null && $orderTax != 0.0) {
            return null;
        }
        return $invoiced < 0 ? min(0.0, $invoiced - $refunded) : max(0.0, $invoiced - $refunded);
    }

    /**
     * Positive VAT on units still refundable, or null when a historic document recorded tax only on the total.
     *
     * @param array<int, array{?float, float}> $taxes
     * @param array<int, float> $quantities
     */
    private function getPositiveLineTaxLeft(array $taxes, array $quantities): ?float
    {
        $positive = null;
        foreach ($taxes as $id => [$invoiced, $refunded]) {
            if ($invoiced != 0.0) {
                $positive ??= 0.0;
                if ($quantities[$id] > 0) {
                    $positive += max(0.0, $invoiced - max(0.0, $refunded));
                }
            }
        }
        return $positive;
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

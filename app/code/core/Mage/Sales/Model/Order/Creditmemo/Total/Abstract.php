<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

abstract class Mage_Sales_Model_Order_Creditmemo_Total_Abstract extends Mage_Sales_Model_Order_Total_Abstract
{
    /**
     * Collect credit memo subtotal
     *
     * @return Mage_Sales_Model_Order_Creditmemo_Total_Abstract
     */
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        return $this;
    }

    protected function hasFixedTax(Mage_Sales_Model_Order_Creditmemo_Item|Mage_Sales_Model_Order_Invoice_Item|Mage_Sales_Model_Order_Item $item): bool
    {
        return (bool) ($item->getWeeeTaxAppliedRowAmount() || $item->getBaseWeeeTaxAppliedRowAmount()
            || $item->getWeeeTaxAppliedAmount() || $item->getBaseWeeeTaxAppliedAmount()
            || Mage::helper('core/string')->unserialize($item->getWeeeTaxApplied()));
    }

    /**
     * @param array<Mage_Sales_Model_Order_Creditmemo> $credits
     * @return array<int, ?float>
     */
    protected function getRefundedTaxWithoutShipping(array $credits, Mage_Sales_Model_Order $order, bool $base): array
    {
        $amounts = $missing = [];
        $shipping = $knownTax = 0.0;
        $tax = $base ? $order->getBaseShippingTaxRefunded() : $order->getShippingTaxRefunded();
        $consistent = $tax !== null;
        foreach ($credits as $credit) {
            $id = $credit->getId();
            $header = $base ? $credit->getBaseTaxAmount() : $credit->getTaxAmount();
            $shippingTax = $base ? $credit->getBaseShippingTaxAmount() : $credit->getShippingTaxAmount();
            $principal = $base ? $credit->getBaseShippingAmount() : $credit->getShippingAmount();
            $shipping += $principal;
            if ($shippingTax === null && $principal > 0.0 && $header !== null) {
                $total = $base ? $credit->getBaseGrandTotal() : $credit->getGrandTotal();
                $refund = $base ? $credit->getBaseAdjustmentPositive() : $credit->getAdjustmentPositive();
                $fee = $base ? $credit->getBaseAdjustmentNegative() : $credit->getAdjustmentNegative();
                $knownAdjustments = ($refund !== null || !($base ? $credit->getAdjustmentPositive() : $credit->getBaseAdjustmentPositive()))
                    && ($fee !== null || !($base ? $credit->getAdjustmentNegative() : $credit->getBaseAdjustmentNegative()));
                if ($total !== null && $knownAdjustments && round($total - $principal - $refund + $fee, 4) === $header
                    && !($base ? $credit->getBaseSubtotal() : $credit->getSubtotal())
                    && !($base ? $credit->getBaseDiscountAmount() : $credit->getDiscountAmount())
                    && !$credit->getAllItems()) {
                    $shippingTax = $header;
                }
            }
            if ($shippingTax === null && ($credit->getBaseShippingAmount() || $credit->getShippingAmount())) {
                $missing[$id] = $header;
                $amounts[$id] = null;
            } else {
                $amounts[$id] = $header === null ? null : round($header - $shippingTax, 4);
                $knownTax += $shippingTax;
                $consistent = $consistent && (float) $shippingTax >= min(0.0, $tax) && (float) $shippingTax <= max(0.0, $tax);
            }
        }
        // Only one shipping credit can own the difference from the order's refunded shipping VAT.
        if (count($missing) === 1 && $consistent) {
            $id = array_key_first($missing);
            $refunded = $base ? $order->getBaseShippingRefunded() : $order->getShippingRefunded();
            $remainder = round($tax - $knownTax, 4);
            if ($missing[$id] !== null && $refunded !== null && round($shipping - $refunded, 4) === 0.0
                && $remainder >= min(0.0, $tax) && $remainder <= max(0.0, $tax) && $this->invoicesCoverShippingTax($order, $base, $tax)) {
                $amounts[$id] = round($missing[$id] - $remainder, 4);
            }
        }
        return $amounts;
    }

    private function invoicesCoverShippingTax(Mage_Sales_Model_Order $order, bool $base, float $tax): bool
    {
        $minimum = $maximum = 0.0;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->isCanceled()) {
                continue;
            }
            $charged = $base ? $invoice->getBaseShippingTaxAmount() : $invoice->getShippingTaxAmount();
            if ($charged === null && ($base ? $invoice->getBaseShippingAmount() : $invoice->getShippingAmount())) {
                return false;
            }
            $minimum += min(0.0, (float) $charged);
            $maximum += max(0.0, (float) $charged);
        }
        return $tax >= round($minimum, 4) && $tax <= round($maximum, 4);
    }

    /**
     * Return the part of $amount that a refund of $baseShipping more shipping takes.
     * It counts on all the shipping refunded so far, so the parts of all the credit memos add up to $amount.
     */
    protected function _getShippingShare(Mage_Sales_Model_Order_Creditmemo $creditmemo, float $amount, float $baseShipping): float
    {
        $order = $creditmemo->getOrder();
        $baseOrderShipping = (float) $order->getBaseShippingAmount();
        if (!$amount || !$baseShipping || $baseOrderShipping <= 0) {
            return 0.0;
        }

        $store = $creditmemo->getStore();
        $baseRefunded = (float) $order->getBaseShippingRefunded();
        $before = $store->roundPrice($amount * min($baseRefunded, $baseOrderShipping) / $baseOrderShipping);
        $after = $store->roundPrice($amount * min($baseRefunded + $baseShipping, $baseOrderShipping) / $baseOrderShipping);

        return $store->roundPrice($after - $before);
    }
}

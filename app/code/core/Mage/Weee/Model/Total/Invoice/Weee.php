<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

class Mage_Weee_Model_Total_Invoice_Weee extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    /**
     * Weee tax collector
     *
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $store = $invoice->getStore();
        $order = $invoice->getOrder();
        $helper = Mage::helper('weee');
        $allocation = new Mage_Weee_Model_DocumentAllocation();
        $previous = $source = null;
        $itemShares = [];

        $totalTax = 0;
        $baseTotalTax = 0;
        $weeeInclTax = 0;
        $baseWeeeInclTax = 0;

        foreach ($invoice->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            $orderItemQty = $orderItem->getQtyOrdered();

            if (!$orderItemQty || !$item->getQty() || $orderItem->isDummy()
                || !$allocation->hasFee($orderItem)
            ) {
                continue;
            }

            $previous ??= $allocation->invoiceAmounts($order, except: $invoice->getId() ? (int) $invoice->getId() : null);
            $source ??= $allocation->orderAmounts($order);
            $share = $allocation->share(
                $source[(int) $orderItem->getId()],
                $previous[$orderItem->getId()] ?? null,
                (float) $item->getQty(),
                $store,
            );
            $itemShares[] = [$item, $share];
            $totalTax += $share['gross'] - $share['discount'];
            $baseTotalTax += $share['base_gross'] - $share['base_discount'];
        }

        $last = $invoice->isLast();
        $requestedTax = $totalTax;
        $requestedBaseTax = $baseTotalTax;
        $included = $source === null ? null : $allocation->feeWasIncludedInSubtotal($order);
        if ($last) {
            // The subtotal and tax collectors have already booked the signed remainder.
            $totalTax = 0.0;
            $baseTotalTax = 0.0;
        } elseif ($included ?? $helper->includeInSubtotal($store)) {
            $allowedSubtotal = $order->getSubtotal() - $order->getSubtotalInvoiced() - $invoice->getSubtotal();
            $allowedBaseSubtotal = $order->getBaseSubtotal() - $order->getBaseSubtotalInvoiced()
                - $invoice->getBaseSubtotal();
            $totalTax = min($allowedSubtotal, $totalTax);
            $baseTotalTax = min($allowedBaseSubtotal, $baseTotalTax);

            $invoice->setSubtotal($invoice->getSubtotal() + $totalTax);
            $invoice->setBaseSubtotal($invoice->getBaseSubtotal() + $baseTotalTax);
        } else {
            $allowedTax = $order->getTaxAmount() - $order->getTaxInvoiced() - $invoice->getTaxAmount();
            $allowedBaseTax = $order->getBaseTaxAmount() - $order->getBaseTaxInvoiced() - $invoice->getBaseTaxAmount();
            $totalTax = min($allowedTax, $totalTax);
            $baseTotalTax = min($allowedBaseTax, $baseTotalTax);

            $invoice->setTaxAmount($invoice->getTaxAmount() + $totalTax);
            $invoice->setBaseTaxAmount($invoice->getBaseTaxAmount() + $baseTotalTax);
        }

        $excess = $last ? 0.0 : $store->roundPrice($requestedTax - $store->roundPrice($totalTax));
        $baseExcess = $last ? 0.0 : $store->roundPrice($requestedBaseTax - $store->roundPrice($baseTotalTax));
        foreach ($itemShares as [$item, $share]) {
            $reduction = min(max(0.0, $share['gross'] - $share['discount']), max(0.0, $excess));
            $baseReduction = min(max(0.0, $share['base_gross'] - $share['base_discount']), max(0.0, $baseExcess));
            $share['gross'] = $store->roundPrice($share['gross'] - $reduction);
            $share['base_gross'] = $store->roundPrice($share['base_gross'] - $baseReduction);
            $excess = $store->roundPrice($excess - $reduction);
            $baseExcess = $store->roundPrice($baseExcess - $baseReduction);
            $allocation->apply($item, $share, $store);
            [$inclTax, $baseInclTax] = $allocation->getTaxInclusiveAmounts($item);
            $weeeInclTax += $inclTax;
            $baseWeeeInclTax += $baseInclTax;
        }
        $invoice->setSubtotalInclTax($invoice->getSubtotalInclTax() + $weeeInclTax);
        $invoice->setBaseSubtotalInclTax($invoice->getBaseSubtotalInclTax() + $baseWeeeInclTax);

        $invoice->setGrandTotal($invoice->getGrandTotal() + $totalTax);
        $invoice->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTotalTax);

        return $this;
    }
}

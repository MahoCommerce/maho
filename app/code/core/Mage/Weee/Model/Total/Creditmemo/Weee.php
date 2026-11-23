<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

class Mage_Weee_Model_Total_Creditmemo_Weee extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    /**
     * @return $this|Mage_Sales_Model_Order_Creditmemo_Total_Abstract
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $store = $creditmemo->getStore();
        $order = $creditmemo->getOrder();
        $invoiceId = (int) ($creditmemo->getInvoice()?->getId() ?? $creditmemo->getInvoiceId());
        $except = $creditmemo->getId() ? (int) $creditmemo->getId() : null;
        $orderInvoices = $orderMemos = $invoice = $invoiceMemos = $orderAmounts = null;
        $helper = Mage::helper('weee');
        $allocation = new Mage_Weee_Model_DocumentAllocation();

        $totalTax              = 0;
        $baseTotalTax          = 0;

        $weeeTaxAmount = 0;
        $baseWeeeTaxAmount = 0;

        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if (!$item->getQty() || $orderItem->isDummy()
                || !$orderItem->getQtyOrdered() || !$allocation->hasFee($orderItem)
            ) {
                continue;
            }
            $id = $orderItem->getId();
            $orderMemos ??= $allocation->creditmemoAmounts($order, except: $except);
            // An order-level credit has already consumed units without naming an invoice.
            if ($invoiceId && empty($orderMemos[$id]['has_unlinked'])) {
                $invoice ??= $allocation->invoiceAmounts($order, $invoiceId);
                $invoiceMemos ??= $allocation->creditmemoAmounts($order, $invoiceId, $except);
                $source = $invoice;
                $previous = $invoiceMemos;
            } else {
                $orderInvoices ??= $allocation->invoiceAmounts($order);
                $source = $orderInvoices;
                $previous = $orderMemos;
            }
            if (!isset($source[$id])) {
                $orderAmounts ??= $allocation->orderAmounts($order);
                $source[$id] = $orderAmounts[(int) $id];
            }
            $share = $allocation->share(
                $source[$id],
                $previous[$id] ?? null,
                (float) $item->getQty(),
                $store,
            );
            [$inclTax, $baseInclTax] = $allocation->apply($item, $share, $store);
            $totalTax += $share['gross'] - $share['discount'];
            $baseTotalTax += $share['base_gross'] - $share['base_discount'];
            $weeeTaxAmount += $inclTax;
            $baseWeeeTaxAmount += $baseInclTax;
        }

        /*
         * please refer the description in weee - invoice section for reasoning
         */

        $included = $orderMemos === null ? null : $allocation->feeWasIncludedInSubtotal($order);
        if ($included ?? $helper->includeInSubtotal($store)) {
            $creditmemo->setSubtotal($creditmemo->getSubtotal() + $totalTax);
            $creditmemo->setBaseSubtotal($creditmemo->getBaseSubtotal() + $baseTotalTax);
        } else {
            $creditmemo->setTaxAmount($creditmemo->getTaxAmount() + $totalTax);
            $creditmemo->setBaseTaxAmount($creditmemo->getBaseTaxAmount() + $baseTotalTax);
        }

        //Increment the subtotal
        $creditmemo->setSubtotalInclTax($creditmemo->getSubtotalInclTax() + $weeeTaxAmount);
        $creditmemo->setBaseSubtotalInclTax($creditmemo->getBaseSubtotalInclTax() + $baseWeeeTaxAmount);

        //Increment the grand total
        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() + $totalTax);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() + $baseTotalTax);

        return $this;
    }
}

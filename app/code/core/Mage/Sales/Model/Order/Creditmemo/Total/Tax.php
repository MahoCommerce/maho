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
        if (!$signedTax || !$signedBaseTax) {
            foreach ($order->getAllItems() as $line) {
                if (!$line->isDummy()) {
                    $refunded = (float) $line->getTaxRefunded();
                    $baseRefunded = (float) $line->getBaseTaxRefunded();
                    $signedTax = $signedTax || $refunded < 0.0 || $refunded > (float) $line->getTaxInvoiced();
                    $signedBaseTax = $signedBaseTax || $baseRefunded < 0.0 || $baseRefunded > (float) $line->getBaseTaxInvoiced();
                }
            }
        }
        if ($invoice && (!$signedTax || !$signedBaseTax)) {
            $sharedItems = [];
            foreach ($invoice->getAllItems() as $line) {
                $signedTax = $signedTax || (float) $line->getTaxAmount() < 0.0;
                $signedBaseTax = $signedBaseTax || (float) $line->getBaseTaxAmount() < 0.0;
                $orderItem = $line->getOrderItem();
                if ($line->getQty() < $orderItem->getQtyInvoiced()
                    && ($line->getTaxAmount() || $line->getBaseTaxAmount() || $orderItem->getTaxInvoiced() || $orderItem->getBaseTaxInvoiced())) {
                    $sharedItems[] = $line->getOrderItemId();
                }
            }
            // A split shipping discount is refunded against the tax on the same invoice.
            if ($sharedItems && $creditmemo->getData('is_split_shipping_discount_invoice_refund')) {
                $signedTax = $signedBaseTax = true;
            }
            if ($sharedItems && $order->getId() && (!$signedTax || !$signedBaseTax)) {
                // One invoice can carry negative tax even when the item's total over all invoices is positive.
                $resource = Mage::getSingleton('core/resource');
                $connection = $resource->getConnection('core_write');
                $minimum = $connection->fetchRow($connection->select()
                    ->from(['item' => $resource->getTableName('sales/invoice_item')], [
                        'tax' => new Maho\Db\Expr('MIN(item.tax_amount)'), 'base_tax' => new Maho\Db\Expr('MIN(item.base_tax_amount)'),
                    ])
                    ->join(['invoice' => $resource->getTableName('sales/invoice')], 'invoice.entity_id = item.parent_id', [])
                    ->where('invoice.order_id = ?', $order->getId())
                    ->where('invoice.state IS NULL OR invoice.state <> ?', Mage_Sales_Model_Order_Invoice::STATE_CANCELED)
                    ->where('item.order_item_id IN (?)', $sharedItems));
                $signedTax = $signedTax || (float) $minimum['tax'] < 0.0;
                $signedBaseTax = $signedBaseTax || (float) $minimum['base_tax'] < 0.0;
            }
        }
        $invoiceTaxes = $refundedOnHeaders = [];
        if ($signedTax || $signedBaseTax) {
            $itemInvoice = $invoice ?: $this->getWholeItemInvoice($creditmemo, $order);
            [$taxLeft, $baseTaxLeft, $positiveTax, $basePositiveTax, $last, $invoiceTaxes, $refundedOnHeaders] = $this->getSignedItemTaxLeft($creditmemo, $itemInvoice);
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
            $source = $invoiceTaxes[$item->getOrderItemId()] ?? null;
            $prior = $refundedOnHeaders[$item->getOrderItemId()] ?? [];
            $tax = $orderItemTax - ($prior['regular'] ?? $orderItem->getTaxRefunded());
            $baseTax = $baseOrderItemTax - ($prior['base'] ?? $orderItem->getBaseTaxRefunded());

            if ($orderItemQty && ($orderItemTax || $baseOrderItemTax || $tax || $baseTax || $orderItemHiddenTax || $baseOrderItemHiddenTax
                || !empty($source['regular']) || !empty($source['base']))) {
                /**
                 * Check item tax amount
                 */
                $hiddenTax      = $orderItemHiddenTax - $orderItem->getHiddenTaxRefunded();
                $baseHiddenTax  = $baseOrderItemHiddenTax - $orderItem->getBaseHiddenTaxRefunded();
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
        $baseGrandTotal = $creditmemo->getBaseGrandTotal() + $baseTaxRefund + $baseTotalHiddenTax;
        if ($creditmemo->hasData('base_split_shipping_discount_left') || $creditmemo->hasData('base_split_shipping_discount_capacity')) {
            $discount = Mage::getModel('sales/order_creditmemo_total_discount')->getShippingDiscountAfterTax(
                $creditmemo,
                $baseTaxRefund + $baseTotalHiddenTax,
                $baseGrandTotal,
            );
            $creditmemo->setBaseDiscountAmount($creditmemo->getBaseDiscountAmount() - $discount);
            $baseGrandTotal -= $discount;
        }
        $creditmemo->setBaseGrandTotal($baseGrandTotal);
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

    /** @return array{float, float, ?float, ?float, bool, array<int, array{qty: float, regular: ?float, base: ?float}>, array<int, array<string, float>>} */
    private function getSignedItemTaxLeft(Mage_Sales_Model_Order_Creditmemo $creditmemo, ?Mage_Sales_Model_Order_Invoice $invoice): array
    {
        $quantities = [];
        $lineTaxes = $baseLineTaxes = $refundedOnHeaders = [];
        $order = $creditmemo->getOrder();
        if ($invoice) {
            $shared = [];
            foreach ($invoice->getAllItems() as $item) {
                $orderItem = $item->getOrderItem();
                if (!$orderItem->isDummy()) {
                    $id = $item->getOrderItemId();
                    $quantities[$id] = (float) $item->getQty();
                    // When the invoice billed all units, the order item also records its invoice tax.
                    $onlyInvoice = $item->getQty() === $orderItem->getQtyInvoiced();
                    $lineTaxes[$id] = [$item->getTaxAmount() ?? ($onlyInvoice ? $orderItem->getTaxInvoiced() : null), 0.0];
                    $baseLineTaxes[$id] = [$item->getBaseTaxAmount() ?? ($onlyInvoice ? $orderItem->getBaseTaxInvoiced() : null), 0.0];
                    if (!$onlyInvoice && $orderItem->getQtyRefunded() > 0) {
                        $shared[$id] = [$lineTaxes[$id][0], $baseLineTaxes[$id][0]];
                    }
                }
            }
            $tax = $invoice->getTaxAmount() - $invoice->getShippingTaxAmount();
            $baseTax = $invoice->getBaseTaxAmount() - $invoice->getBaseShippingTaxAmount();
            $previous = Mage::getResourceModel('sales/order_creditmemo_collection')->setOrderFilter($order)->setOrder('entity_id', 'ASC');
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
                $itemTax = $memo->getTaxAmount() - $memo->getShippingTaxAmount();
                $baseItemTax = $memo->getBaseTaxAmount() - $memo->getBaseShippingTaxAmount();
                $tax -= $itemTax;
                $baseTax -= $baseItemTax;
                $items = $memo->getAllItems();
                foreach ($items as $item) {
                    $id = $item->getOrderItemId();
                    if (isset($quantities[$id])) {
                        $share = $quantities[$id] > 0.0 ? $item->getQty() / $quantities[$id] : 0.0;
                        $useHeader = count($items) === 1 && ($item->getTaxAmount() === null || $item->getBaseTaxAmount() === null)
                            && !$this->hasFixedTax($item) && !$this->hasFixedTax($order->getItemById($id));
                        $lineTaxes[$id][1] = $this->getPriorLineTax($lineTaxes[$id], $item->getTaxAmount(), $share, $useHeader ? $itemTax : null, $order->getStore());
                        $baseLineTaxes[$id][1] = $this->getPriorLineTax($baseLineTaxes[$id], $item->getBaseTaxAmount(), $share, $useHeader ? $baseItemTax : null, $order->getStore());
                        $quantities[$id] -= $item->getQty();
                    }
                }
            }
            if ($shared && $priorMemos) {
                // A previous credit on another invoice may have used part of this invoice's VAT.
                $balances = $this->getSharedTaxBalances($invoice, $priorMemos, $shared);
                foreach ($balances as $id => $balance) {
                    $tax -= $this->removeUsedTax($lineTaxes[$id], $balance['remaining'][0], $balance['unpaid'][0]);
                    $baseTax -= $this->removeUsedTax($baseLineTaxes[$id], $balance['remaining'][1], $balance['unpaid'][1]);
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
            $refundedOnHeaders = $this->getTaxRefundedOnHeaders($creditmemo, $order);
            foreach ($order->getAllItems() as $item) {
                if (!$item->isDummy()) {
                    $id = $item->getId();
                    $quantities[$id] = $item->getQtyToRefund();
                    $lineTaxes[$id] = [(float) $item->getTaxInvoiced(), $refundedOnHeaders[$id]['regular'] ?? (float) $item->getTaxRefunded()];
                    $baseLineTaxes[$id] = [(float) $item->getBaseTaxInvoiced(), $refundedOnHeaders[$id]['base'] ?? (float) $item->getBaseTaxRefunded()];
                }
            }
        }
        $positiveTax = $this->getPositiveLineTaxLeft($lineTaxes, $quantities);
        $basePositiveTax = $this->getPositiveLineTaxLeft($baseLineTaxes, $quantities);
        if (!$invoice) {
            $positiveTax = $this->includeOtherTax($positiveTax, $tax, $lineTaxes, $order->getTaxRefunded() - $order->getShippingTaxRefunded());
            $basePositiveTax = $this->includeOtherTax($basePositiveTax, $baseTax, $baseLineTaxes, $order->getBaseTaxRefunded() - $order->getBaseShippingTaxRefunded());
        }
        $hasItems = false;
        $invoiceTaxes = [];
        $hasLineTax = $invoice && array_any($lineTaxes, static fn(array $line): bool => $line[0] != 0.0);
        $hasBaseLineTax = $invoice && array_any($baseLineTaxes, static fn(array $line): bool => $line[0] != 0.0);
        foreach ($creditmemo->getAllItems() as $item) {
            $id = $item->getOrderItemId();
            if ($invoice && $creditmemo->getInvoice() && $item->getQty() > 0 && !$item->getOrderItem()->isDummy()) {
                $invoiceTaxes[$id] = ['qty' => $quantities[$id] ?? 0.0, 'regular' => null, 'base' => null];
                if ($item->getQty() <= $invoiceTaxes[$id]['qty']) {
                    if ($positiveTax !== null || ($hasLineTax && $lineTaxes[$id][0] !== null)) {
                        $invoiceTaxes[$id]['regular'] = $this->getInvoiceLineBalance($lineTaxes[$id], (float) $item->getOrderItem()->getTaxInvoiced());
                    }
                    if ($basePositiveTax !== null || ($hasBaseLineTax && $baseLineTaxes[$id][0] !== null)) {
                        $invoiceTaxes[$id]['base'] = $this->getInvoiceLineBalance($baseLineTaxes[$id], (float) $item->getOrderItem()->getBaseTaxInvoiced());
                    }
                }
            }
            if ($item->getQty() > 0 && isset($quantities[$id])) {
                $quantities[$id] -= $item->getQty();
                $hasItems = true;
            }
        }
        return $hasItems ? [$tax, $baseTax, $positiveTax, $basePositiveTax, round(max($quantities), 4) <= 0.0, $invoiceTaxes, $refundedOnHeaders] : [0.0, 0.0, 0.0, 0.0, true, [], []];
    }

    /** @return array<int, array<string, float>> */
    private function getTaxRefundedOnHeaders(Mage_Sales_Model_Order_Creditmemo $creditmemo, Mage_Sales_Model_Order $order): array
    {
        if (!array_any($order->getAllItems(), static fn($item): bool => $item->getQtyRefunded() > 0)) {
            return [];
        }
        $memos = Mage::getResourceModel('sales/order_creditmemo_collection')->setOrderFilter($order)->setOrder('entity_id', 'ASC')
            ->addFieldToFilter('state', ['neq' => Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED])->getItems();
        unset($memos[$creditmemo->getId()]);
        if (!$memos) {
            return [];
        }
        $lines = Mage::getResourceModel('sales/order_creditmemo_item_collection')
            ->addFieldToFilter('parent_id', ['in' => array_keys($memos)]);
        $byMemo = $linkedQuantities = [];
        foreach ($lines as $line) {
            $memoId = $line->getParentId();
            $byMemo[$memoId][] = $line;
            $invoiceId = $memos[$memoId]->getInvoiceId();
            if ($invoiceId && $line->getQty() > 0.0) {
                $id = $line->getOrderItemId();
                $linkedQuantities[$invoiceId][$id] = ($linkedQuantities[$invoiceId][$id] ?? 0.0) + $line->getQty();
            }
        }
        $recorded = $missing = $unknown = $quantities = $inferred = [];
        $invoices = $order->getInvoiceCollection()->getItems();
        foreach ($memos as $memoId => $memo) {
            $items = $byMemo[$memoId] ?? [];
            foreach ($items as $item) {
                $id = $item->getOrderItemId();
                $orderItem = $order->getItemById($id);
                if (!$orderItem || $orderItem->isDummy() || !$item->getQty()) {
                    continue;
                }
                $qtyLeft = round($orderItem->getQtyInvoiced() - ($quantities[$id] ?? 0.0), 4);
                $quantities[$id] = ($quantities[$id] ?? 0.0) + $item->getQty();
                $single = count($items) === 1 && !$this->hasFixedTax($item) && !$this->hasFixedTax($orderItem);
                $invoice = $invoices[$memo->getInvoiceId()] ?? null;
                $linked = $linkedQuantities[$memo->getInvoiceId()] ?? [];
                $source = $single && $invoice && !$invoice->isCanceled() && count($linked) === 1 && isset($linked[$id]) ? $invoice->getAllItems() : [];
                $sourceLine = count($source) === 1 && $source[0]->getOrderItemId() == $id && round($linked[$id] - $source[0]->getQty(), 4) <= 0.0
                    ? $source[0] : null;
                foreach (['regular' => $item->getTaxAmount(), 'base' => $item->getBaseTaxAmount()] as $type => $amount) {
                    $header = $type === 'base' ? $memo->getBaseTaxAmount() : $memo->getTaxAmount();
                    if (!$single || $qtyLeft < $item->getQty() || $header === null) {
                        $unknown[$id][$type] = true;
                        continue;
                    }
                    $paid = round($header - ($type === 'base' ? $memo->getBaseShippingTaxAmount() : $memo->getShippingTaxAmount()), 4);
                    if ($amount !== null) {
                        if ($amount !== $paid) {
                            $unknown[$id][$type] = true;
                        } else {
                            $recorded[$id][$type] = ($recorded[$id][$type] ?? 0.0) + $amount;
                        }
                        continue;
                    }
                    $invoiced = $type === 'base' ? $orderItem->getBaseTaxInvoiced() : $orderItem->getTaxInvoiced();
                    $taxLeft = round($invoiced - ($recorded[$id][$type] ?? 0.0) - ($missing[$id][$type] ?? 0.0), 4);
                    $expected = $qtyLeft > $item->getQty()
                        ? $taxLeft / $qtyLeft * $item->getQty()
                        : $taxLeft;
                    $direct = false;
                    if ($sourceLine && $this->getInvoiceRowFee($sourceLine, $type) === 0.0) {
                        $sourceTax = $type === 'base' ? $sourceLine->getBaseTaxAmount() : $sourceLine->getTaxAmount();
                        if ($sourceLine->getQty() == $item->getQty()) {
                            $direct = $sourceTax === $paid && ($type === 'base'
                                ? $invoice->getBaseGrandTotal() === $memo->getBaseGrandTotal() && $invoice->getBaseTaxAmount() !== null
                                    && round($invoice->getBaseTaxAmount() - $invoice->getBaseShippingTaxAmount(), 4) === $paid
                                : $invoice->getGrandTotal() === $memo->getGrandTotal() && $invoice->getTaxAmount() !== null
                                    && round($invoice->getTaxAmount() - $invoice->getShippingTaxAmount(), 4) === $paid);
                        } elseif ($sourceLine->getQty() > $item->getQty() && $qtyLeft == $orderItem->getQtyInvoiced() && $sourceTax !== null
                            && $this->getSingleRowHeaderTax($invoice, $sourceLine, $type) === $sourceTax
                            && $this->getSingleRowHeaderTax($memo, $item, $type) === $paid) {
                            $share = $sourceTax * $item->getQty() / $sourceLine->getQty();
                            $direct = round($share, 4) === $paid || $order->getStore()->roundPrice($share) === $paid;
                        }
                    }
                    if (!$direct) {
                        $inferred[$id][$type] = true;
                    }
                    if (!$direct && $paid !== round($expected, 4)
                        && ($qtyLeft === $item->getQty() || $paid !== $order->getStore()->roundPrice($expected))) {
                        $unknown[$id][$type] = true;
                    } else {
                        $missing[$id][$type] = ($missing[$id][$type] ?? 0.0) + $paid;
                    }
                }
            }
        }
        $confirmed = [];
        foreach ($missing as $id => $amounts) {
            $item = $order->getItemById($id);
            foreach ($amounts as $type => $amount) {
                if (isset($unknown[$id][$type]) || round($quantities[$id] - $item->getQtyRefunded(), 4) != 0.0) {
                    continue;
                }
                $previous = $type === 'base' ? $item->getBaseTaxRefunded() : $item->getTaxRefunded();
                $booked = $recorded[$id][$type] ?? 0.0;
                $paid = round($booked + $amount, 4);
                if ($previous >= min($booked, $paid) && $previous <= max($booked, $paid) && $paid != $previous) {
                    $confirmed[$id][$type] = $paid;
                }
            }
        }
        if ($confirmed) {
            // A rate inferred from the order is usable only if every invoice actually charged it.
            $quantities = $billed = [];
            foreach ($invoices as $invoice) {
                if ($invoice->isCanceled()) {
                    continue;
                }
                $tax = $invoice->getTaxAmount() - $invoice->getShippingTaxAmount();
                $baseTax = $invoice->getBaseTaxAmount() - $invoice->getBaseShippingTaxAmount();
                $items = $invoice->getAllItems();
                $fees = ['regular' => 0.0, 'base' => 0.0];
                $lineFees = [];
                $subtotal = $invoice->getSubtotal();
                $baseSubtotal = $invoice->getBaseSubtotal();
                foreach ($items as $line) {
                    $tax -= $line->getTaxAmount();
                    $baseTax -= $line->getBaseTaxAmount();
                    $subtotal -= $line->getRowTotal();
                    $baseSubtotal -= $line->getBaseRowTotal();
                    foreach (['regular', 'base'] as $type) {
                        $net = $this->getInvoiceRowFee($line, $type);
                        $lineFees[$line->getId()][$type] = $net;
                        $fees[$type] = $net === null || $fees[$type] === null ? null : $fees[$type] + $net;
                    }
                }
                $unexplained = [
                    'regular' => $invoice->getTaxAmount() === null || (round($tax, 4) != 0.0
                        && ($fees['regular'] === null || round($subtotal, 4) != 0.0 || round($tax - $fees['regular'], 4) != 0.0)),
                    'base' => $invoice->getBaseTaxAmount() === null || (round($baseTax, 4) != 0.0
                        && ($fees['base'] === null || round($baseSubtotal, 4) != 0.0 || round($baseTax - $fees['base'], 4) != 0.0)),
                ];
                foreach ($items as $line) {
                    $id = $line->getOrderItemId();
                    if (isset($confirmed[$id])) {
                        $quantities[$id] = ($quantities[$id] ?? 0.0) + $line->getQty();
                        foreach ($confirmed[$id] as $type => $amount) {
                            $lineTax = $type === 'base' ? $line->getBaseTaxAmount() : $line->getTaxAmount();
                            if ($lineTax === null && count($items) === 1 && $lineFees[$line->getId()][$type] === 0.0) {
                                $lineTax = $this->getSingleRowHeaderTax($invoice, $line, $type);
                            }
                            $billed[$id][$type] = ($billed[$id][$type] ?? 0.0) + $lineTax;
                            if ($lineTax === null || (isset($inferred[$id][$type]) && ($unexplained[$type] || $lineFees[$line->getId()][$type] !== 0.0
                                || !$this->matchesInvoicedItemTax($order->getItemById($id), $type, $line->getQty(), $lineTax)))) {
                                unset($confirmed[$id][$type]);
                            }
                        }
                    }
                }
            }
            foreach ($confirmed as $id => $amounts) {
                if (round(($quantities[$id] ?? 0.0) - $order->getItemById($id)->getQtyInvoiced(), 4) != 0.0) {
                    unset($confirmed[$id]);
                    continue;
                }
                foreach ($amounts as $type => $amount) {
                    $item = $order->getItemById($id);
                    $ledger = $type === 'base' ? $item->getBaseTaxInvoiced() : $item->getTaxInvoiced();
                    if ($ledger === null || round(($billed[$id][$type] ?? 0.0) - $ledger, 4) != 0.0) {
                        unset($confirmed[$id][$type]);
                    }
                }
            }
        }
        return $confirmed;
    }

    private function getInvoiceRowFee(Mage_Sales_Model_Order_Invoice_Item $line, string $type): ?float
    {
        $base = $type === 'base';
        $gross = (float) ($base ? $line->getBaseWeeeTaxAppliedRowAmount() : $line->getWeeeTaxAppliedRowAmount());
        $unit = (float) ($base ? $line->getBaseWeeeTaxAppliedAmount() : $line->getWeeeTaxAppliedAmount());
        $details = Mage::helper('core/string')->unserialize($line->getWeeeTaxApplied());
        if (!$details) {
            return $gross == 0.0 && $unit == 0.0 ? 0.0 : null;
        }
        if (!is_array($details)) {
            return null;
        }
        $rowKey = $base ? 'base_row_amount' : 'row_amount';
        $discountKey = $base ? 'base_weee_discount' : 'weee_discount';
        $total = $discount = 0.0;
        foreach ($details as $fee) {
            if (!is_array($fee) || !is_numeric($fee[$rowKey] ?? null)) {
                return null;
            }
            $lineDiscount = $fee[$discountKey] ?? null;
            if (!array_key_exists($discountKey, $fee) && $gross == 0.0 && $unit == 0.0 && $fee[$rowKey] == 0.0
                && ($fee[$base ? 'base_amount' : 'amount'] ?? 0.0) == 0.0
                && ($fee[$base ? 'total_base_weee_discount' : 'total_weee_discount'] ?? 0.0) == 0.0) {
                $lineDiscount = 0.0;
            }
            if (!is_numeric($lineDiscount)) {
                return null;
            }
            if (($fee['weee_document_gross_allocated'] ?? false) !== true
                && ($fee[$rowKey] != 0.0 || $lineDiscount != 0.0 || $unit != 0.0)) {
                return null;
            }
            $total += $fee[$rowKey];
            $discount += $lineDiscount;
        }
        return round($total - $gross, 4) == 0.0 ? round($gross - $discount, 4) : null;
    }

    private function getSingleRowHeaderTax(
        Mage_Sales_Model_Order_Invoice|Mage_Sales_Model_Order_Creditmemo $document,
        Mage_Sales_Model_Order_Invoice_Item|Mage_Sales_Model_Order_Creditmemo_Item $line,
        string $type,
    ): ?float {
        $base = $type === 'base';
        $tax = $base ? $document->getBaseTaxAmount() : $document->getTaxAmount();
        $subtotal = $base ? $document->getBaseSubtotal() : $document->getSubtotal();
        $row = $base ? $line->getBaseRowTotal() : $line->getRowTotal();
        $total = $base ? $document->getBaseGrandTotal() : $document->getGrandTotal();
        if ($tax === null || $subtotal === null || $total === null || $row !== $subtotal
            || ($base ? $document->getBaseShippingAmount() : $document->getShippingAmount())
            || ($base ? $document->getBaseShippingTaxAmount() : $document->getShippingTaxAmount())
            || ($base ? $document->getBaseDiscountAmount() : $document->getDiscountAmount())
            || ($document instanceof Mage_Sales_Model_Order_Creditmemo && ($base
                ? $document->getBaseAdjustmentPositive() || $document->getBaseAdjustmentNegative()
                : $document->getAdjustmentPositive() || $document->getAdjustmentNegative()))) {
            return null;
        }
        return round($total - $subtotal, 4) === $tax ? $tax : null;
    }

    private function matchesInvoicedItemTax(Mage_Sales_Model_Order_Item $item, string $type, float $qty, ?float $tax): bool
    {
        $invoiced = $type === 'base' ? $item->getBaseTaxInvoiced() : $item->getTaxInvoiced();
        return $tax !== null && $item->getQtyInvoiced() > 0
            && round($invoiced * $qty / $item->getQtyInvoiced(), 4) === round($tax, 4);
    }

    /**
     * @param list<Mage_Sales_Model_Order_Creditmemo> $credits
     * @param array<int, array{?float, ?float}> $source
     * @return array<int, array{remaining: array{?float, ?float}, unpaid: array{float, float}}>
     */
    private function getSharedTaxBalances(Mage_Sales_Model_Order_Invoice $invoice, array $credits, array $source): array
    {
        $order = $invoice->getOrder();
        $history = array_fill_keys(array_keys($source), [
            'invoices' => [], 'credits' => [], 'invoices_qty' => 0.0, 'credits_qty' => 0.0, 'paid' => [],
        ]);
        foreach (['invoices' => $order->getInvoiceCollection(), 'credits' => $credits] as $kind => $documents) {
            foreach ($documents as $document) {
                if ($document instanceof Mage_Sales_Model_Order_Invoice && $document->isCanceled()) {
                    continue;
                }
                $items = $document->getAllItems();
                foreach ($items as $item) {
                    $id = $item->getOrderItemId();
                    if (!isset($source[$id])) {
                        continue;
                    }
                    $amounts = [$item->getTaxAmount(), $item->getBaseTaxAmount()];
                    $history[$id][$kind][] = $amounts;
                    $history[$id][$kind . '_qty'] += $item->getQty();
                    if ($document instanceof Mage_Sales_Model_Order_Creditmemo) {
                        $paid = [null, null];
                        if ($document->getInvoiceId() != $invoice->getId() && count($items) === 1
                            && !$this->hasFixedTax($item) && !$this->hasFixedTax($order->getItemById($id))) {
                            $paid = [
                                $document->getTaxAmount() === null ? null : $document->getTaxAmount() - $document->getShippingTaxAmount(),
                                $document->getBaseTaxAmount() === null ? null : $document->getBaseTaxAmount() - $document->getBaseShippingTaxAmount(),
                            ];
                        }
                        $history[$id]['paid'][] = $paid;
                    }
                }
            }
        }
        $result = [];
        foreach ($source as $id => $taxes) {
            $rows = $history[$id];
            $item = $order->getItemById($id);
            $billed = [$item->getTaxInvoiced(), $item->getBaseTaxInvoiced()];
            $credited = [$item->getTaxRefunded(), $item->getBaseTaxRefunded()];
            $allBilled = round($rows['invoices_qty'] - $item->getQtyInvoiced(), 4) == 0.0;
            $allCredited = round($rows['credits_qty'] - $item->getQtyRefunded(), 4) == 0.0;
            $remaining = $unpaid = [];
            foreach ($taxes as $currency => $tax) {
                [$charged] = $this->getTaxWithSign(array_column($rows['invoices'], $currency), $tax, $allBilled ? $billed[$currency] : null);
                [$refunded, $unpaid[$currency]] = $this->getTaxWithSign(
                    array_column($rows['credits'], $currency),
                    $tax,
                    $allCredited ? $credited[$currency] : null,
                    array_column($rows['paid'], $currency),
                );
                $remaining[$currency] = $charged !== null && $refunded !== null ? $charged - $refunded : null;
            }
            $result[$id] = ['remaining' => [$remaining[0], $remaining[1]], 'unpaid' => [$unpaid[0], $unpaid[1]]];
        }
        return $result;
    }

    /**
     * @param list<?float> $amounts
     * @param list<?float> $paid VAT paid on each independently attributable credit
     * @return array{?float, float} Recorded VAT and the part the credit did not pay
     */
    private function getTaxWithSign(array $amounts, ?float $source, ?float $total, array $paid = []): array
    {
        $missing = array_keys($amounts, null, true);
        if ($missing) {
            if ($total === null) {
                return [null, 0.0];
            }
            $difference = round($total - array_sum($amounts), 4);
            if (count($missing) === 1) {
                // Keep the recovered amount with the payment on that same credit.
                $amounts[$missing[0]] = $difference;
            } elseif ($difference != 0.0 || !array_all($missing, static fn(int $index): bool => ($paid[$index] ?? null) === 0.0)) {
                return [null, 0.0];
            }
        }
        $recorded = $unpaid = 0.0;
        foreach ($amounts as $index => $amount) {
            if ($amount * $source > 0.0) {
                $recorded += $amount;
                if (isset($paid[$index])) {
                    $unpaid += $amount < 0.0
                        ? min(0.0, $amount - min(0.0, $paid[$index]))
                        : max(0.0, $amount - max(0.0, $paid[$index]));
                }
            }
        }
        return [$recorded, $unpaid];
    }

    /** @param array{?float, ?float} $line */
    private function removeUsedTax(array &$line, ?float $remaining, float $unpaid): float
    {
        [$invoiced, $refunded] = $line;
        if ($remaining === null || $invoiced == 0.0 || $refunded === null) {
            return 0.0;
        }
        $used = round($invoiced - $refunded - ($invoiced < 0.0 ? min(0.0, $remaining) : max(0.0, $remaining)), 4);
        $used = $invoiced < 0.0 ? min(0.0, $used) : max(0.0, $used);
        $line[0] -= $used;
        return $invoiced < 0.0 ? min(0.0, $used - $unpaid) : max(0.0, $used - $unpaid);
    }

    /** @param array{?float, ?float} $line */
    private function getPriorLineTax(array $line, ?float $amount, float $share, ?float $singleItemTax, Mage_Core_Model_Store $store): ?float
    {
        [$invoiced, $refunded] = $line;
        if ($invoiced == 0.0 || $refunded === null) {
            return $refunded;
        }
        // A single-line historic credit can confirm its share even when it saved tax only on the header.
        if ($amount === null) {
            if ($singleItemTax === null || $store->roundPrice(($invoiced - $refunded) * $share) != round($singleItemTax, 4)) {
                return null;
            }
            $amount = $singleItemTax;
        }
        return $refunded + ($invoiced < 0.0 ? min(0.0, $amount) : max(0.0, $amount));
    }

    /** @param array{?float, ?float} $line */
    private function getInvoiceLineBalance(array $line, float $orderTax): ?float
    {
        [$invoiced, $refunded] = $line;
        if ($refunded === null || ($invoiced === null && $orderTax != 0.0)) {
            return null;
        }
        return $invoiced < 0 ? min(0.0, $invoiced - $refunded) : max(0.0, $invoiced - $refunded);
    }

    /** @param array<int, array{?float, float}> $lineTaxes */
    private function includeOtherTax(?float $positive, float $left, array $lineTaxes, float $refunded): ?float
    {
        if ($positive === null) {
            return null;
        }
        // Fees can leave positive tax on the order even when the returned merchandise has negative VAT.
        $invoicedOutsideLines = $left + $refunded - array_sum(array_column($lineTaxes, 0));
        $refundedOutsideLines = $refunded - array_sum(array_column($lineTaxes, 1));
        return $positive + max(0.0, $invoicedOutsideLines - max(0.0, $refundedOutsideLines));
    }

    /**
     * Positive VAT on units still refundable, or null when a historic document recorded tax only on the total.
     *
     * @param array<int, array{?float, ?float}> $taxes
     * @param array<int, float> $quantities
     */
    private function getPositiveLineTaxLeft(array $taxes, array $quantities): ?float
    {
        $positive = null;
        foreach ($taxes as $id => [$invoiced, $refunded]) {
            if ($refunded === null && $quantities[$id] > 0) {
                return null;
            }
            if ($invoiced != 0.0 || $refunded != 0.0) {
                $positive ??= 0.0;
                if ($quantities[$id] > 0) {
                    $positive += max(0.0, $invoiced - $refunded);
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

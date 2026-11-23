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
                    $signedTax = $signedTax || (float) $line->getTaxInvoiced() < 0.0;
                    $signedBaseTax = $signedBaseTax || (float) $line->getBaseTaxInvoiced() < 0.0;
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

            if ($orderItemQty && ($orderItemTax || $baseOrderItemTax || $orderItemHiddenTax || $baseOrderItemHiddenTax
                || !empty($source['regular']) || !empty($source['base']))) {
                /**
                 * Check item tax amount
                 */
                $prior = $refundedOnHeaders[$item->getOrderItemId()] ?? [];
                $tax            = $orderItemTax - ($prior['regular'] ?? $orderItem->getTaxRefunded());
                $baseTax        = $baseOrderItemTax - ($prior['base'] ?? $orderItem->getBaseTaxRefunded());
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
        $shippingDiscountLeft = (float) $creditmemo->getData('base_split_shipping_discount_left');
        if ($shippingDiscountLeft > 0.0) {
            $discount = max(0.0, min($shippingDiscountLeft, round($baseGrandTotal, 4), round($baseTaxRefund + $baseTotalHiddenTax, 4)));
            $creditmemo->setBaseDiscountAmount($creditmemo->getBaseDiscountAmount() - $discount);
            $creditmemo->unsetData('base_split_shipping_discount_left');
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
        foreach ($creditmemo->getAllItems() as $item) {
            $id = $item->getOrderItemId();
            if ($invoice && $creditmemo->getInvoice() && $item->getQty() > 0 && !$item->getOrderItem()->isDummy()) {
                $invoiceTaxes[$id] = ['qty' => $quantities[$id] ?? 0.0, 'regular' => null, 'base' => null];
                if ($item->getQty() <= $invoiceTaxes[$id]['qty']) {
                    if ($positiveTax !== null || $lineTaxes[$id][0] != 0.0) {
                        $invoiceTaxes[$id]['regular'] = $this->getInvoiceLineBalance($lineTaxes[$id], (float) $item->getOrderItem()->getTaxInvoiced());
                    }
                    if ($basePositiveTax !== null || $baseLineTaxes[$id][0] != 0.0) {
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
        $memos = Mage::getResourceModel('sales/order_creditmemo_collection')->setOrderFilter($order)
            ->addFieldToFilter('state', ['neq' => Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED])->getItems();
        unset($memos[$creditmemo->getId()]);
        if (!$memos) {
            return [];
        }
        $lines = Mage::getResourceModel('sales/order_creditmemo_item_collection')
            ->addFieldToFilter('parent_id', ['in' => array_keys($memos)]);
        $byMemo = [];
        foreach ($lines as $line) {
            $byMemo[$line->getParentId()][] = $line;
        }
        $recorded = $missing = [];
        foreach ($byMemo as $memoId => $items) {
            $memo = $memos[$memoId];
            foreach ($items as $item) {
                $id = $item->getOrderItemId();
                $orderItem = $order->getItemById($id);
                if (!$orderItem || $orderItem->isDummy() || !$item->getQty()) {
                    continue;
                }
                foreach (['regular' => $item->getTaxAmount(), 'base' => $item->getBaseTaxAmount()] as $type => $amount) {
                    if ($amount !== null) {
                        $recorded[$id][$type] = ($recorded[$id][$type] ?? 0.0) + $amount;
                    } elseif (count($items) === 1 && !$this->hasFixedTax($item) && !$this->hasFixedTax($orderItem)) {
                        // Shipping VAT is separate. All remaining VAT on this credit belongs to its single item.
                        $amount = $type === 'base' ? $memo->getBaseTaxAmount() - $memo->getBaseShippingTaxAmount()
                            : $memo->getTaxAmount() - $memo->getShippingTaxAmount();
                        $missing[$id][$type] = ($missing[$id][$type] ?? 0.0) + $amount;
                    }
                }
            }
        }
        $confirmed = [];
        foreach ($missing as $id => $amounts) {
            $item = $order->getItemById($id);
            foreach ($amounts as $type => $amount) {
                $previous = $type === 'base' ? $item->getBaseTaxRefunded() : $item->getTaxRefunded();
                $booked = $recorded[$id][$type] ?? 0.0;
                $paid = round($booked + $amount, 4);
                if ($previous >= min($booked, $paid) && $previous <= max($booked, $paid) && $paid != $previous) {
                    $confirmed[$id][$type] = $paid;
                }
            }
        }
        return $confirmed;
    }

    /**
     * @param list<Mage_Sales_Model_Order_Creditmemo> $credits
     * @param array<int, array{?float, ?float}> $source
     * @return array<int, array{remaining: array{?float, ?float}, unpaid: array{float, float}}>
     */
    private function getSharedTaxBalances(Mage_Sales_Model_Order_Invoice $invoice, array $credits, array $source): array
    {
        $order = $invoice->getOrder();
        $balances = array_fill_keys(array_keys($source), ['remaining' => [0.0, 0.0], 'unpaid' => [0.0, 0.0]]);
        foreach ([1 => $order->getInvoiceCollection(), -1 => $credits] as $direction => $documents) {
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
                    $paid = null;
                    if ($document instanceof Mage_Sales_Model_Order_Creditmemo && $document->getInvoiceId() != $invoice->getId()
                        && count($items) === 1 && !$this->hasFixedTax($item) && !$this->hasFixedTax($order->getItemById($id))) {
                        $paid = [
                            $document->getTaxAmount() - $document->getShippingTaxAmount(),
                            $document->getBaseTaxAmount() - $document->getBaseShippingTaxAmount(),
                        ];
                    }
                    foreach ([$item->getTaxAmount(), $item->getBaseTaxAmount()] as $currency => $amount) {
                        if ($direction === 1 && $amount === null) {
                            $balances[$id]['remaining'][$currency] = null;
                        } elseif ($balances[$id]['remaining'][$currency] !== null && $amount * $source[$id][$currency] > 0.0) {
                            $balances[$id]['remaining'][$currency] += $direction * $amount;
                            if ($paid !== null) {
                                // A line can record VAT that the historic credit never paid to the customer.
                                $balances[$id]['unpaid'][$currency] += $amount < 0.0
                                    ? min(0.0, $amount - min(0.0, $paid[$currency]))
                                    : max(0.0, $amount - max(0.0, $paid[$currency]));
                            }
                        }
                    }
                }
            }
        }
        return $balances;
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

    private function hasFixedTax(Mage_Sales_Model_Order_Creditmemo_Item|Mage_Sales_Model_Order_Item $item): bool
    {
        return (bool) ($item->getWeeeTaxAppliedRowAmount() || $item->getBaseWeeeTaxAppliedRowAmount()
            || $item->getWeeeTaxAppliedAmount() || $item->getBaseWeeeTaxAppliedAmount()
            || Mage::helper('core/string')->unserialize($item->getWeeeTaxApplied()));
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

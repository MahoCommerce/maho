<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Order_Creditmemo_Total_Discount extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    private const float MINOR_UNIT = 0.01;

    /**
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $creditmemo->setDiscountAmount(0);
        $creditmemo->setBaseDiscountAmount(0);

        $order = $creditmemo->getOrder();

        $totalDiscountAmount = 0;
        $baseTotalDiscountAmount = 0;

        /**
         * Calculate how much shipping discount should be applied
         * basing on how much shipping should be refunded.
         */
        $baseShippingAmount = (float) $creditmemo->getBaseShippingAmount();
        $totalDiscountAmount += $this->_getShippingShare($creditmemo, (float) $order->getShippingDiscountAmount(), $baseShippingAmount);

        /** @var Mage_Sales_Model_Order_Creditmemo_Item $item */
        foreach ($creditmemo->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();

            if ($orderItem->isDummy()) {
                continue;
            }

            $orderItemDiscount      = (float) $orderItem->getDiscountInvoiced();
            $baseOrderItemDiscount  = (float) $orderItem->getBaseDiscountInvoiced();
            $orderItemQty           = $orderItem->getQtyInvoiced();

            if ($orderItemQty && ($orderItemDiscount || $baseOrderItemDiscount)) {
                $discount = $orderItemDiscount - $orderItem->getDiscountRefunded();
                $baseDiscount = $baseOrderItemDiscount - $orderItem->getBaseDiscountRefunded();
                if (!$item->isLast()) {
                    $availableQty = $orderItemQty - $orderItem->getQtyRefunded();
                    if ($availableQty > 0) {
                        $discount = $creditmemo->roundPrice(
                            $discount / $availableQty * $item->getQty(),
                            'regular',
                            true,
                        );
                        $baseDiscount = $creditmemo->roundPrice(
                            $baseDiscount / $availableQty * $item->getQty(),
                            'base',
                            true,
                        );
                    }
                }

                $totalDiscountAmount += $discount;
                $baseTotalDiscountAmount += $baseDiscount;

                $item->setDiscountAmount($discount);
                $item->setBaseDiscountAmount($baseDiscount);
            }
        }

        $capacity = max(0.0, round((float) $creditmemo->getBaseGrandTotal() - $baseTotalDiscountAmount, 4));
        $baseTotalDiscountAmount += $this->getBaseShippingDiscount($creditmemo, $capacity);
        $creditmemo->setDiscountAmount(-$totalDiscountAmount);
        $creditmemo->setBaseDiscountAmount(-$baseTotalDiscountAmount);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - $totalDiscountAmount);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - $baseTotalDiscountAmount);
        return $this;
    }

    public function getShippingDiscountAfterTax(Mage_Sales_Model_Order_Creditmemo $memo, float $tax, float $grandTotal): float
    {
        $capacity = $memo->getData('base_split_shipping_discount_capacity');
        if ($capacity !== null) {
            $capacity = max(0.0, min(round($grandTotal, 4), round($capacity + $tax, 4)));
            $discount = $this->getBaseShippingDiscount($memo, $capacity, includeTax: true);
        } else {
            $discount = max(0.0, min((float) $memo->getData('base_split_shipping_discount_left'), round($grandTotal, 4), round($tax, 4)));
        }
        $memo->unsetData('base_split_shipping_discount_left');
        return $discount;
    }

    private function getBaseShippingDiscount(Mage_Sales_Model_Order_Creditmemo $memo, float $capacity, bool $includeTax = false): float
    {
        $memo->unsetData('is_split_shipping_discount_invoice_refund');
        $memo->unsetData('base_split_shipping_discount_left');
        $memo->unsetData('base_split_shipping_discount_capacity');
        $order = $memo->getOrder();
        $shipping = (float) $order->getBaseShippingAmount();
        $shippingDiscount = (float) $order->getBaseShippingDiscountAmount();
        $refundedShipping = (float) $memo->getBaseShippingAmount();
        $default = $this->_getShippingShare($memo, $shippingDiscount, $refundedShipping);
        if ($order->getShippingAmount() || $order->getShippingDiscountAmount() || $shipping <= 0.0 || $shippingDiscount <= 0.0
            || $memo->getStore()->roundPrice($shippingDiscount) !== $shippingDiscount
        ) {
            return $default;
        }
        $source = $this->getSplitDiscountInvoice($order, $shipping, $shippingDiscount);
        if (!$source) {
            return $default;
        }
        $invoice = $memo->getInvoice();
        if ($invoice && !$invoice->isCanceled()
            && ($invoice->getId() === $source->getId() || (float) $invoice->getBaseShippingAmount() === $shipping)
        ) {
            $memo->setData('is_split_shipping_discount_invoice_refund', true);
        }
        $previous = [];
        $used = 0.0;
        foreach ($order->getCreditmemosCollection() ?: [] as $row) {
            if ($row->getId() === $memo->getId() || $row->getState() === Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED) {
                continue;
            }
            $discount = $this->getBaseDiscountOutsideItems($row);
            if ($discount === null || $discount < 0.0) {
                return $default;
            }
            $previous[] = $row;
            $used += $discount;
        }
        $remaining = max(0.0, round($shippingDiscount - $used, 4));
        $store = $memo->getStore();
        if ($store->roundPrice($source->getBaseTotalRefunded()) > $store->roundPrice($source->getBaseGrandTotal())) {
            return min($default, $remaining);
        }
        $default = $invoice && $invoice->getBaseShippingAmount() ? 0.0 : min($default, $remaining);
        if ($remaining === 0.0) {
            return 0.0;
        }

        $shippingLeft = round($shipping - (float) $order->getBaseShippingRefunded(), 4);
        if ($invoice && $refundedShipping !== min($shippingLeft, (float) $invoice->getBaseShippingAmount())) {
            return $default;
        }

        $adjusted = $memo->getBaseAdjustmentPositive() || $memo->getBaseAdjustmentNegative()
            || $memo->getAdjustmentPositive() || $memo->getAdjustmentNegative();
        if (($invoice && $invoice->getId() === $source->getId())
            || (!$invoice && !$adjusted && $refundedShipping === 0.0 && $shippingLeft === 0.0)
        ) {
            $share = $this->getRefundedItemDiscount($memo, $source, $previous, $shippingDiscount, $used, $capacity, $includeTax);
            if ($share !== null) {
                return $this->applyShippingDiscount($memo, max(0.0, min($share, $remaining)), $capacity);
            }
        }
        if ($adjusted) {
            return $default;
        }
        if ($refundedShipping === $shippingLeft && $this->refundsRemainingItems($memo)) {
            return $this->applyShippingDiscount($memo, $remaining, $capacity);
        }
        return $invoice && $invoice->getId() !== $source->getId() ? 0.0 : $default;
    }

    private function applyShippingDiscount(Mage_Sales_Model_Order_Creditmemo $memo, float $share, float $capacity): float
    {
        if ($share > $capacity) {
            // The tax collector can cover the rest with tax refunded on this document.
            $memo->setData('base_split_shipping_discount_left', round($share - $capacity, 4));
        }
        return min($share, $capacity);
    }

    private function getSplitDiscountInvoice(Mage_Sales_Model_Order $order, float $shipping, float $discount): ?Mage_Sales_Model_Order_Invoice
    {
        $hasShippingInvoice = false;
        $source = null;
        foreach ($order->getInvoiceCollection() as $invoice) {
            if ($invoice->isCanceled()) {
                continue;
            }
            $invoiceShipping = (float) $invoice->getBaseShippingAmount();
            $invoiceDiscount = $this->getBaseDiscountOutsideItems($invoice);
            if ($invoice->getShippingAmount() || $invoiceDiscount === null) {
                return null;
            }
            if ($invoiceShipping !== 0.0) {
                if ($hasShippingInvoice || $invoiceShipping !== $shipping || $invoiceDiscount !== 0.0) {
                    return null;
                }
                $hasShippingInvoice = true;
            } elseif ($invoiceDiscount !== 0.0) {
                if ($source || $invoiceDiscount !== $discount) {
                    return null;
                }
                $source = $invoice;
            }
        }
        return $hasShippingInvoice ? $source : null;
    }

    /** @param array<Mage_Sales_Model_Order_Creditmemo> $previous */
    private function getRefundedItemDiscount(
        Mage_Sales_Model_Order_Creditmemo $memo,
        Mage_Sales_Model_Order_Invoice $source,
        array $previous,
        float $discount,
        float $used,
        float $merchandiseCapacity,
        bool $includeTax,
    ): ?float {
        $order = $memo->getOrder();
        $invoiced = $this->getItemAmounts($source, $order, $includeTax);
        $current = $refunded = $this->getItemAmounts($memo, $order, $includeTax);
        $otherItems = null;
        if (!$memo->getInvoice()) {
            $otherItems = $this->getItemsOnOtherInvoices($order, $source);
            if (!$current || array_diff_key($current, $invoiced) || array_intersect_key($current, $otherItems)) {
                return null;
            }
        }
        $history = [];
        $paidTaxes = $includeTax ? $this->getRefundedTaxWithoutShipping($previous, $order, true) : [];
        if ($includeTax) {
            usort($previous, static fn(Mage_Sales_Model_Order_Creditmemo $first, Mage_Sales_Model_Order_Creditmemo $second): int
                => $first->getId() <=> $second->getId());
        }
        foreach ($previous as $row) {
            if ($row->getInvoiceId() && $row->getInvoiceId() !== (int) $source->getId()) {
                continue;
            }
            $amounts = array_intersect_key($this->getItemAmounts($row, $order, $includeTax, $source, $history, $paidTaxes[$row->getId()] ?? null), $invoiced);
            if (!$row->getInvoiceId() && $amounts) {
                $otherItems ??= $this->getItemsOnOtherInvoices($order, $source);
                if (array_intersect_key($amounts, $otherItems)) {
                    return null;
                }
            }
            foreach ($amounts as $id => $amount) {
                $refunded[$id] = [
                    'net' => ($refunded[$id]['net'] ?? 0.0) + $amount['net'],
                    'qty' => ($refunded[$id]['qty'] ?? 0.0) + $amount['qty'],
                ];
            }
        }
        if ($invoiced && array_all(
            $invoiced,
            static fn(array $amount, int $id): bool
            => round($refunded[$id]['net'] ?? 0.0, 4) >= $amount['net']
            && round($refunded[$id]['qty'] ?? 0.0, 4) >= round($amount['qty'], 4),
        )) {
            return round($discount - $used, 4);
        }
        $total = round(array_sum(array_column($invoiced, 'net')), 4);
        $store = $memo->getStore();
        if (!$includeTax && $total < $discount) {
            $taxedItems = $this->getItemAmounts($source, $order, includeTax: true);
            $gross = round(array_sum(array_column($taxedItems, 'net')), 4);
            if ($gross > $total) {
                // Partial quantities can refund different tax cents on the same invoice line.
                $memo->setData('base_split_shipping_discount_capacity', $merchandiseCapacity);
                return 0.0;
            }
        }
        if ($total <= 0.0) {
            return null;
        }
        $proportional = !array_any($invoiced, static fn(array $amount): bool
            => $amount['net'] > 0.0 && $amount['net'] * (1 - $discount / $total) < $amount['qty'] * self::MINOR_UNIT);
        $lineDiscounts = $proportional && !$includeTax ? [] : $this->getWholeUnitDiscounts($order, $store, $invoiced, $discount);
        $net = $target = $capacity = $futureCapacity = 0.0;
        foreach ($invoiced as $id => $amount) {
            $refundedNet = min($amount['net'], $refunded[$id]['net'] ?? 0.0);
            $net += $refundedNet;
            if (isset($lineDiscounts[$id])) {
                $refundedQty = min($amount['qty'], $refunded[$id]['qty'] ?? 0.0);
                $available = $amount['net'] - $amount['qty'] * self::MINOR_UNIT;
                if ($available > 0.0) {
                    $refundedCapacity = max(0.0, $refundedNet - $refundedQty * self::MINOR_UNIT);
                    $futureCapacity += max(0.0, $available - $refundedCapacity);
                    $target += $store->roundPrice($lineDiscounts[$id] * min(1.0, $refundedCapacity / $available));
                }
                $capacity += max(0.0, ($current[$id]['net'] ?? 0.0) - ($current[$id]['qty'] ?? 0.0) * self::MINOR_UNIT);
            }
        }
        if ($proportional || $lineDiscounts === []) {
            $target = $store->roundPrice($discount * min(1.0, $net / $total));
            if ($lineDiscounts === []) {
                $capacity = $discount;
            } else {
                // Later units must be able to cover the rest and still refund at least one cent each.
                $target = max($target, $store->roundPrice($discount - $futureCapacity));
            }
        }
        // The older collector could deduct a share on a different credit memo.
        return min(round($capacity, 4), $store->roundPrice($target - $used));
    }

    /**
     * @param array<int, array{net: float, qty: float}> $invoiced
     * @return array<int, float>
     */
    private function getWholeUnitDiscounts(
        Mage_Sales_Model_Order $order,
        Mage_Core_Model_Store $store,
        array $invoiced,
        float $discount,
    ): array {
        $positive = array_filter($invoiced, static fn(array $amount): bool => $amount['net'] > 0.0);
        $capacity = [];
        foreach ($positive as $id => $amount) {
            $reserve = $store->roundPrice($amount['qty'] * self::MINOR_UNIT);
            if (floor($amount['qty']) !== $amount['qty'] || $order->getItemById($id)->getIsQtyDecimal()
                || $store->roundPrice($amount['net']) !== $amount['net'] || $amount['net'] < $reserve
            ) {
                return [];
            }
            $capacity[$id] = $store->roundPrice($amount['net'] - $reserve);
        }
        $available = $store->roundPrice(array_sum($capacity));
        if ($available < $discount) {
            return [];
        }
        // Keep one minor unit per whole item when the invoice has enough to do so.
        ksort($capacity);
        $running = $allocated = 0.0;
        $shares = [];
        foreach ($capacity as $id => $amount) {
            $running += $amount;
            $target = $store->roundPrice($discount * min(1.0, $running / $available));
            $shares[$id] = $store->roundPrice($target - $allocated);
            $allocated = $target;
        }
        return $shares;
    }

    /**
     * @param array<int, array{float, ?float}> $history
     * @return array<int, array{net: float, qty: float}>
     */
    private function getItemAmounts(
        Mage_Sales_Model_Order_Invoice|Mage_Sales_Model_Order_Creditmemo $document,
        Mage_Sales_Model_Order $order,
        bool $includeTax = false,
        ?Mage_Sales_Model_Order_Invoice $source = null,
        array &$history = [],
        ?float $paidTax = null,
    ): array {
        $amounts = [];
        $taxes = [];
        $known = true;
        $items = $document->getAllItems();
        foreach ($items as $item) {
            $id = $item->getOrderItemId();
            $orderItem = $id ? $order->getItemById($id) : null;
            if (!$orderItem || !$item->getQty() || $orderItem->isDummy()) {
                continue;
            }
            $net = (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount();
            if ($includeTax) {
                $tax = $item->getBaseTaxAmount();
                if ($tax === null && $source && $document instanceof Mage_Sales_Model_Order_Creditmemo
                    && !$this->hasFixedTax($item) && !$this->hasFixedTax($orderItem)
                ) {
                    [$priorQty, $priorTax] = $history[$id] ?? [0.0, 0.0];
                    $tax = $this->getHistoricItemTax($document, $source, $item, $priorQty, $priorTax, count($items) === 1, $paidTax);
                }
                if ($source) {
                    $taxes[$id] = ($taxes[$id] ?? 0.0) + $tax;
                    $known = $known && $tax !== null;
                }
                $net += $tax + $item->getBaseHiddenTaxAmount();
            }
            $amounts[$id] = [
                'net' => round(($amounts[$id]['net'] ?? 0.0) + max(0.0, $net), 4),
                'qty' => (float) $item->getQty() + ($amounts[$id]['qty'] ?? 0.0),
            ];
        }
        if ($taxes && $document instanceof Mage_Sales_Model_Order_Creditmemo) {
            $known = $known && $paidTax !== null && round(array_sum($taxes) - $paidTax, 4) === 0.0;
            foreach ($taxes as $id => $tax) {
                [$qty, $priorTax] = $history[$id] ?? [0.0, 0.0];
                $history[$id] = [$qty + $amounts[$id]['qty'], $known && $priorTax !== null ? $priorTax + $tax : null];
            }
        }
        return $amounts;
    }

    private function getHistoricItemTax(
        Mage_Sales_Model_Order_Creditmemo $memo,
        Mage_Sales_Model_Order_Invoice $source,
        Mage_Sales_Model_Order_Creditmemo_Item $item,
        float $priorQty,
        ?float $priorTax,
        bool $singleItem,
        ?float $paidTax,
    ): ?float {
        if ($priorTax === null || ($singleItem && $paidTax === null) || $memo->getBaseTaxAmount() === null || $source->getBaseTaxAmount() === null || $item->getQty() <= 0.0) {
            return null;
        }
        $expected = null;
        $outsideItems = $source->getBaseTaxAmount() - $source->getBaseShippingTaxAmount();
        foreach ($source->getAllItems() as $line) {
            if ($line->getBaseTaxAmount() === null) {
                return null;
            }
            $outsideItems -= $line->getBaseTaxAmount();
            if ($line->getOrderItemId() === $item->getOrderItemId()) {
                $qtyLeft = round($line->getQty() - $priorQty, 4);
                $invoicedTax = $line->getBaseTaxAmount();
                $priorTax = round($priorTax, 4);
                if ($expected !== null || $qtyLeft < $item->getQty() || $this->hasFixedTax($line)
                    || (!$singleItem && $invoicedTax !== 0.0)
                    || $priorTax < min(0.0, $invoicedTax) || $priorTax > max(0.0, $invoicedTax)
                ) {
                    return null;
                }
                $expected = ($invoicedTax - $priorTax) * $item->getQty() / $qtyLeft;
            }
        }
        // On a multi-item credit only an exempt invoice line proves its own tax independently of the header.
        $paid = $singleItem ? $paidTax : 0.0;
        return round($outsideItems, 4) === 0.0 && $expected !== null
            && ($paid === round($expected, 4) || $paid === $memo->getStore()->roundPrice($expected)) ? $paid : null;
    }

    /** @return array<int, true> */
    private function getItemsOnOtherInvoices(Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $source): array
    {
        $items = [];
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (!$invoice->isCanceled() && $invoice->getId() !== $source->getId()) {
                foreach ($invoice->getAllItems() as $item) {
                    if ($item->getOrderItemId() && $item->getQty()) {
                        $items[$item->getOrderItemId()] = true;
                    }
                }
            }
        }
        return $items;
    }

    private function refundsRemainingItems(Mage_Sales_Model_Order_Creditmemo $memo): bool
    {
        $quantities = null;
        if ($memo::class === Mage_Sales_Model_Order_Creditmemo::class) {
            $quantities = [];
            foreach ($memo->getItemsCollection() as $item) {
                $quantities[(int) $item->getOrderItemId()] ??= (float) $item->getQty();
            }
        }

        foreach ($memo->getOrder()->getAllItems() as $orderItem) {
            if ($quantities === null) {
                $item = $memo->getItemByOrderId($orderItem->getId());
                $qty = $item ? (float) $item->getQty() : 0.0;
            } else {
                $qty = $quantities[(int) $orderItem->getId()] ?? 0.0;
            }
            if ($orderItem->getQtyToInvoice() > 0 || round($qty - $orderItem->getQtyToRefund(), 4) < 0.0) {
                return false;
            }
        }
        return true;
    }
}

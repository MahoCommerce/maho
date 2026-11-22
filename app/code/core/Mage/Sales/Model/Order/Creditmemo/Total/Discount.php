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

        $baseTotalDiscountAmount += $this->getBaseShippingDiscount($creditmemo, (float) $baseTotalDiscountAmount);
        $creditmemo->setDiscountAmount(-$totalDiscountAmount);
        $creditmemo->setBaseDiscountAmount(-$baseTotalDiscountAmount);

        $creditmemo->setGrandTotal($creditmemo->getGrandTotal() - $totalDiscountAmount);
        $creditmemo->setBaseGrandTotal($creditmemo->getBaseGrandTotal() - $baseTotalDiscountAmount);
        return $this;
    }

    private function getBaseShippingDiscount(Mage_Sales_Model_Order_Creditmemo $memo, float $itemDiscount): float
    {
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
        $default = min($default, $remaining);
        if ($remaining === 0.0) {
            return 0.0;
        }

        $invoice = $memo->getInvoice();
        $shippingLeft = round($shipping - (float) $order->getBaseShippingRefunded(), 4);
        if (($invoice && $refundedShipping !== min($shippingLeft, (float) $invoice->getBaseShippingAmount()))
            || $memo->getBaseAdjustmentPositive() || $memo->getBaseAdjustmentNegative()
            || $memo->getAdjustmentPositive() || $memo->getAdjustmentNegative()
        ) {
            return $default;
        }

        $capacity = max(0.0, round((float) $memo->getBaseGrandTotal() - $itemDiscount, 4));
        if ($invoice && $invoice->getId() === $source->getId()) {
            $share = $this->getRefundedItemDiscount($memo, $source, $previous, $shippingDiscount, $used);
            if ($share !== null) {
                return max(0.0, min($share, $remaining, $capacity));
            }
        }
        if ($refundedShipping === $shippingLeft && $this->refundsRemainingItems($memo)) {
            return min($remaining, $capacity);
        }
        return $invoice && $invoice->getId() !== $source->getId() ? 0.0 : $default;
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
    ): ?float {
        $order = $memo->getOrder();
        $invoiced = $this->getItemNetAmounts($source, $order);
        $current = $refunded = $this->getItemNetAmounts($memo, $order);
        $otherItems = null;
        foreach ($previous as $row) {
            if ($row->getInvoiceId() && $row->getInvoiceId() !== (int) $source->getId()) {
                continue;
            }
            $amounts = array_intersect_key($this->getItemNetAmounts($row, $order), $invoiced);
            if (!$row->getInvoiceId() && $amounts) {
                $otherItems ??= $this->getItemsOnOtherInvoices($order, $source);
                if (array_intersect_key($amounts, $otherItems)) {
                    return null;
                }
            }
            foreach ($amounts as $id => $amount) {
                $refunded[$id] = ($refunded[$id] ?? 0.0) + $amount;
            }
        }
        $total = round(array_sum($invoiced), 4);
        if ($total <= 0.0) {
            return null;
        }
        $store = $memo->getStore();
        $lineDiscounts = $this->getIndivisibleItemDiscounts($source, $order, $store, $invoiced, $total, $discount);
        $net = $target = $capacity = 0.0;
        foreach ($invoiced as $id => $amount) {
            $refundedNet = min($amount, $refunded[$id] ?? 0.0);
            $net += $refundedNet;
            if (isset($lineDiscounts[$id])) {
                $target += $store->roundPrice($lineDiscounts[$id] * $refundedNet / $amount);
                $capacity += max(0.0, min($amount, $current[$id] ?? 0.0) - 0.01);
            }
        }
        if ($lineDiscounts === []) {
            $target = $store->roundPrice($discount * min(1.0, $net / $total));
            $capacity = $discount;
        }
        // The older collector could deduct a share on a different credit memo.
        return min(round($capacity, 4), $store->roundPrice($target - $used));
    }

    /**
     * @param array<int, float> $invoiced
     * @return array<int, float>
     */
    private function getIndivisibleItemDiscounts(
        Mage_Sales_Model_Order_Invoice $source,
        Mage_Sales_Model_Order $order,
        Mage_Core_Model_Store $store,
        array $invoiced,
        float $total,
        float $discount,
    ): array {
        $positive = array_filter($invoiced, static fn(float $amount): bool => $amount > 0.0);
        if (!array_any($positive, static fn(float $amount): bool => $amount * (1 - $discount / $total) < 0.01)) {
            return [];
        }
        $capacity = [];
        foreach ($source->getAllItems() as $item) {
            $id = $item->getOrderItemId();
            if (!isset($positive[$id])) {
                continue;
            }
            if (isset($capacity[$id]) || (float) $item->getQty() !== 1.0 || $order->getItemById($id)->getIsQtyDecimal()
                || $store->roundPrice($positive[$id]) !== $positive[$id]
            ) {
                return [];
            }
            $capacity[$id] = $store->roundPrice($positive[$id] - 0.01);
        }
        $available = $store->roundPrice(array_sum($capacity));
        if ($available < $discount) {
            return [];
        }
        // Keep one minor unit on each indivisible line when the invoice has enough to do so.
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

    /** @return array<int, float> */
    private function getItemNetAmounts(
        Mage_Sales_Model_Order_Invoice|Mage_Sales_Model_Order_Creditmemo $document,
        Mage_Sales_Model_Order $order,
    ): array {
        $amounts = [];
        foreach ($document->getAllItems() as $item) {
            $id = $item->getOrderItemId();
            $orderItem = $id ? $order->getItemById($id) : null;
            if (!$orderItem || !$item->getQty() || $orderItem->isDummy()) {
                continue;
            }
            $net = max(0.0, (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount());
            $amounts[$id] = round(($amounts[$id] ?? 0.0) + $net, 4);
        }
        return $amounts;
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
        foreach ($memo->getOrder()->getAllItems() as $orderItem) {
            $item = $memo->getItemByOrderId($orderItem->getId());
            $qty = $item ? (float) $item->getQty() : 0.0;
            if ($orderItem->getQtyToInvoice() > 0 || round($qty - $orderItem->getQtyToRefund(), 4) < 0.0) {
                return false;
            }
        }
        return true;
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

/**
 * @phpstan-type FeeAmounts array{gross: float, base_gross: float, discount: float, base_discount: float}
 * @phpstan-type Amounts array{qty: float, gross: float, base_gross: float, discount: float, base_discount: float, fees: array<array-key, FeeAmounts>, has_unlinked: bool}
 * @phpstan-type Share array{gross: float, base_gross: float, discount: float, base_discount: float, fees: array<array-key, FeeAmounts>}
 */
class Mage_Weee_Model_DocumentAllocation
{
    private const array AMOUNTS = ['gross', 'base_gross', 'discount', 'base_discount'];
    private const string UNTITLED_FEE = 'Fixed Product Tax';

    /** @return array<int, Amounts> */
    public function invoiceAmounts(Mage_Sales_Model_Order $order, ?int $invoiceId = null, ?int $except = null): array
    {
        return $this->documentAmounts(
            $order,
            'sales/invoice',
            'sales/invoice_item',
            Mage_Sales_Model_Order_Invoice::STATE_CANCELED,
            'document.entity_id',
            $invoiceId,
            $except,
        );
    }

    /** @return array<int, Amounts> */
    public function creditmemoAmounts(Mage_Sales_Model_Order $order, ?int $invoiceId = null, ?int $except = null): array
    {
        return $this->documentAmounts(
            $order,
            'sales/creditmemo',
            'sales/creditmemo_item',
            Mage_Sales_Model_Order_Creditmemo::STATE_CANCELED,
            'document.invoice_id',
            $invoiceId,
            $except,
        );
    }

    public function hasFee(Mage_Sales_Model_Order_Item $item): bool
    {
        return (bool) ($item->getWeeeTaxAppliedRowAmount() || $item->getBaseWeeeTaxAppliedRowAmount()
            || $item->getWeeeTaxAppliedAmount() || $item->getBaseWeeeTaxAppliedAmount()
            || Mage::helper('weee')->getAppliedToItem($item));
    }

    /** @return array<int, Amounts> */
    public function orderAmounts(Mage_Sales_Model_Order $order): array
    {
        $amounts = [];
        $gross = ['gross' => 0.0, 'base_gross' => 0.0];
        $store = $order->getStore();
        $items = $order->getAllItems();
        usort($items, static fn(Mage_Sales_Model_Order_Item $a, Mage_Sales_Model_Order_Item $b): int => $a->getId() <=> $b->getId());
        foreach ($items as $item) {
            if ($item->isDummy() || !$item->getQtyOrdered() || !$this->hasFee($item)) {
                continue;
            }
            $amount = $this->orderItemAmounts($item, $store);
            foreach (['gross', 'base_gross'] as $key) {
                $amount[$key] = $this->roundGross($gross, $key, $amount[$key], $store);
            }
            $amounts[(int) $item->getId()] = $amount;
        }
        foreach (['gross' => 'discount', 'base_gross' => 'base_discount'] as $key => $discountKey) {
            $grossByItem = array_map(static fn(array $amount): float => $amount[$key], $amounts);
            $discounts = array_map(static fn(array $amount): float => $amount[$discountKey], $amounts);
            foreach ($this->balanceGross($grossByItem, $discounts, $store) as $id => $amount) {
                $amounts[$id][$key] = $amount;
                $this->allocateTitleGross($amounts[$id]['fees'], $key, $amount, $store);
            }
        }
        return $amounts;
    }

    /** @return Amounts */
    private function orderItemAmounts(Mage_Sales_Model_Order_Item $item, Mage_Core_Model_Store $store): array
    {
        $qty = (float) $item->getQtyOrdered();
        $discount = (float) $item->getDiscountAppliedForWeeeTax();
        $baseDiscount = (float) $item->getBaseDiscountAppliedForWeeeTax();
        $helper = Mage::helper('weee');
        $fees = $helper->getAppliedToItem($item);
        return [
            'qty' => $qty,
            'gross' => (float) ($item->getWeeeTaxAppliedRowAmount() ?: $item->getWeeeTaxAppliedAmount() * $qty ?: array_sum(array_column($fees, 'row_amount'))),
            'base_gross' => (float) ($item->getBaseWeeeTaxAppliedRowAmount() ?: $item->getBaseWeeeTaxAppliedAmount() * $qty ?: array_sum(array_column($fees, 'base_row_amount'))),
            'discount' => $discount,
            'base_discount' => $baseDiscount,
            'fees' => $this->feeAmounts($helper->allocateTaxDiscounts($fees, $discount, $baseDiscount, $store)),
            'has_unlinked' => false,
        ];
    }

    /**
     * @param array{gross: float, base_gross: float} $running
     * @param 'gross'|'base_gross' $key
     */
    private function roundGross(array &$running, string $key, float $amount, Mage_Core_Model_Store $store): float
    {
        $previous = $store->roundPrice($running[$key]);
        $running[$key] += $amount;
        return $store->roundPrice($store->roundPrice($running[$key]) - $previous);
    }

    /**
     * The rounded share for all units processed so far includes the remainder
     * left by previous documents, including documents made with the old calculation.
     *
     * @param Amounts $source
     * @param Amounts|null $previous
     * @return Share
     */
    public function share(
        array $source,
        ?array $previous,
        float $qty,
        Mage_Core_Model_Store $store,
    ): array {
        $cumulativeQty = ($previous['qty'] ?? 0.0) + $qty;
        $fraction = $source['qty'] > 0 ? min(1.0, $cumulativeQty / $source['qty']) : 0.0;
        $share = ['gross' => 0.0, 'base_gross' => 0.0, 'discount' => 0.0, 'base_discount' => 0.0, 'fees' => []];
        foreach (self::AMOUNTS as $key) {
            $share[$key] = max(0.0, $store->roundPrice(
                $store->roundPrice($source[$key] * $fraction) - ($previous[$key] ?? 0.0),
            ));
        }
        foreach (['gross' => 'discount', 'base_gross' => 'base_discount'] as $gross => $discount) {
            $net = $source[$gross] - $source[$discount];
            if ($net >= 0.0 && $source[$discount] >= 0.0) {
                $remainingNet = $store->roundPrice($net - ($previous[$gross] ?? 0.0) + ($previous[$discount] ?? 0.0));
                $netShare = max(0.0, min($remainingNet, $share[$gross] - $share[$discount]));
                $share[$gross] = $store->roundPrice($share[$discount] + $netShare);
            }
        }
        foreach ($source['fees'] as $title => $fee) {
            foreach (self::AMOUNTS as $key) {
                $share['fees'][$title][$key] = max(0.0, $fee[$key] - ($previous['fees'][$title][$key] ?? 0.0));
            }
        }
        return $share;
    }

    /** @param Share $share */
    public function apply(
        Mage_Sales_Model_Order_Invoice_Item|Mage_Sales_Model_Order_Creditmemo_Item $item,
        array $share,
        Mage_Core_Model_Store $store,
    ): void {
        $helper = Mage::helper('weee');
        $qty = $item->getQty();
        $orderItem = $item->getOrderItem();
        $applied = $helper->getAppliedToItem($orderItem);
        foreach ($applied as &$fee) {
            foreach (['', 'base_'] as $prefix) {
                $amount = $prefix . 'amount';
                $rowAmount = $prefix . 'row_amount';
                $amountInclTax = $amount . '_incl_tax';
                $rowAmountInclTax = $rowAmount . '_incl_tax';
                $fee[$amount] ??= (float) ($fee[$rowAmount] ?? 0.0) / $orderItem->getQtyOrdered();
                $fee[$amountInclTax] ??= isset($fee[$rowAmountInclTax])
                    ? (float) $fee[$rowAmountInclTax] / $orderItem->getQtyOrdered()
                    : $fee[$amount];
                $fee[$rowAmount] = $fee[$amount] * $qty;
                $fee[$rowAmountInclTax] = $fee[$amountInclTax] * $qty;
            }
            $fee['title'] = (string) ($fee['title'] ?? self::UNTITLED_FEE);
            $fee['weee_document_gross_allocated'] = true;
        }
        unset($fee);
        $originalAmounts = $this->feeAmounts($applied);
        $titleCounts = array_count_values(array_column($applied, 'title'));
        foreach ([
            'gross' => ['row_amount', 'discount', 'weee_discount'],
            'base_gross' => ['base_row_amount', 'base_discount', 'base_weee_discount'],
        ] as $key => [$amountKey, $discount, $discountKey]) {
            foreach ($applied as &$fee) {
                $title = $fee['title'];
                $weight = $originalAmounts[$title][$key];
                $titleDiscount = $share['fees'][$title][$discount] ?? 0.0;
                $fee[$discountKey] = $weight ? $titleDiscount * $fee[$amountKey] / $weight : $titleDiscount / $titleCounts[$title];
            }
            unset($fee);
        }
        $applied = $helper->allocateTaxDiscounts($applied, $share['discount'], $share['base_discount'], $store);
        $discountsByTitle = $this->feeAmounts($applied);
        foreach (['gross' => ['row_amount', 'discount'], 'base_gross' => ['base_row_amount', 'base_discount']] as $key => [$amountKey, $discountKey]) {
            $weights = $share['fees'] ?: $originalAmounts;
            foreach ($weights as $title => &$weight) {
                $weight[$discountKey] = $discountsByTitle[$title][$discountKey] ?? 0.0;
            }
            unset($weight);
            $this->allocateTitleGross($weights, $key, $share[$key], $store);
            $this->writeTitleGross($applied, $weights, $key, $amountKey, $store);
        }

        $item->setWeeeTaxAppliedRowAmount($share['gross']);
        $item->setBaseWeeeTaxAppliedRowAmount($share['base_gross']);
        $item->setWeeeTaxRowDisposition($item->getWeeeTaxDisposition() * $qty);
        $item->setBaseWeeeTaxRowDisposition($item->getBaseWeeeTaxDisposition() * $qty);
        $helper->setApplied($item, $applied);
    }

    /**
     * @param array<array-key, FeeAmounts> $fees
     * @param 'gross'|'base_gross' $key
     */
    private function allocateTitleGross(array &$fees, string $key, float $amount, Mage_Core_Model_Store $store): void
    {
        $gross = $this->allocateGross(array_map(static fn(array $fee): float => $fee[$key], $fees), $amount, $store);
        $discount = $key === 'gross' ? 'discount' : 'base_discount';
        $gross = $this->balanceGross($gross, array_map(static fn(array $fee): float => $fee[$discount], $fees), $store);
        foreach ($fees as $index => &$fee) {
            $fee[$key] = $gross[$index];
        }
        unset($fee);
    }

    /**
     * @param array<array-key, float> $weights
     * @return array<array-key, float>
     */
    private function allocateGross(array $weights, float $amount, Mage_Core_Model_Store $store): array
    {
        $weight = array_sum($weights);
        $last = array_key_last($weights);
        $seen = 0.0;
        $previous = 0.0;
        foreach ($weights as $index => &$share) {
            $seen += $share;
            $rounded = $index === $last ? $amount : ($weight ? $store->roundPrice($amount * $seen / $weight) : 0.0);
            $share = $store->roundPrice($rounded - $previous);
            $previous = $rounded;
        }
        unset($share);
        return $weights;
    }

    /**
     * @param array<array-key, float> $gross
     * @param array<array-key, float> $discounts
     * @return array<array-key, float>
     */
    private function balanceGross(array $gross, array $discounts, Mage_Core_Model_Store $store): array
    {
        $shortage = 0.0;
        foreach ($gross as $key => $amount) {
            $shortage += max(0.0, $discounts[$key] - $amount);
        }
        $shortage = $store->roundPrice($shortage);
        // Keep signed amounts when redistributing gross cannot represent them.
        if ($shortage <= 0.0 || min($gross) < 0.0 || min($discounts) < 0.0
            || $store->roundPrice(array_sum($gross) - array_sum($discounts)) < 0.0
        ) {
            return $gross;
        }
        foreach ($gross as $key => $amount) {
            $discount = $discounts[$key];
            $use = min($shortage, max(0.0, $amount - $discount));
            $gross[$key] = $store->roundPrice(max($amount, $discount) - $use);
            $shortage = $store->roundPrice($shortage - $use);
        }
        return $gross;
    }

    /**
     * @param array<array-key, array<string, mixed>> $applied
     * @param array<array-key, FeeAmounts> $amounts
     * @param 'gross'|'base_gross' $key
     */
    private function writeTitleGross(
        array &$applied,
        array $amounts,
        string $key,
        string $amountKey,
        Mage_Core_Model_Store $store,
    ): void {
        $entriesByTitle = [];
        $discountKey = $key === 'gross' ? 'weee_discount' : 'base_weee_discount';
        $discounts = array_map(static fn(array $fee): float => (float) $fee[$discountKey], $applied);
        foreach ($applied as $index => $fee) {
            $entriesByTitle[$fee['title']][$index] = (float) $fee[$amountKey];
        }
        foreach ($entriesByTitle as $title => $entries) {
            $gross = $this->allocateGross($entries, $amounts[$title][$key] ?? 0.0, $store);
            foreach ($this->balanceGross($gross, array_intersect_key($discounts, $entries), $store) as $index => $amount) {
                $applied[$index][$amountKey . '_incl_tax'] += $amount - $applied[$index][$amountKey];
                $applied[$index][$amountKey] = $amount;
            }
        }
    }

    /**
     * @param array<array-key, array<string, mixed>> $applied
     * @return array<array-key, FeeAmounts>
     */
    private function feeAmounts(array $applied): array
    {
        $fees = [];
        foreach ($applied as $tax) {
            $title = (string) ($tax['title'] ?? self::UNTITLED_FEE);
            $fees[$title] ??= ['gross' => 0.0, 'base_gross' => 0.0, 'discount' => 0.0, 'base_discount' => 0.0];
            $fees[$title]['gross'] += (float) ($tax['row_amount'] ?? 0.0);
            $fees[$title]['base_gross'] += (float) ($tax['base_row_amount'] ?? 0.0);
            $fees[$title]['discount'] += (float) ($tax['weee_discount'] ?? 0.0);
            $fees[$title]['base_discount'] += (float) ($tax['base_weee_discount'] ?? 0.0);
        }
        return $fees;
    }

    /** @return array<int, Amounts> */
    private function documentAmounts(
        Mage_Sales_Model_Order $order,
        string $documentTable,
        string $itemTable,
        int $canceledState,
        string $invoiceColumn,
        ?int $invoiceId,
        ?int $except,
    ): array {
        if (!$order->getId()) {
            return [];
        }

        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $select = $connection->select()
            ->from(['document' => $resource->getTableName($documentTable)], [])
            ->join(
                ['item' => $resource->getTableName($itemTable)],
                'item.parent_id = document.entity_id',
                [
                    'order_item_id', 'qty', 'weee_tax_applied', 'weee_tax_applied_row_amount',
                    'base_weee_tax_applied_row_amnt', 'tax_amount', 'base_tax_amount', 'row_total', 'base_row_total',
                ],
            )
            ->columns([
                'document_id' => 'document.entity_id', 'invoice_id' => $invoiceColumn,
                'document_tax' => 'document.tax_amount', 'document_base_tax' => 'document.base_tax_amount',
                'shipping_tax' => 'document.shipping_tax_amount', 'base_shipping_tax' => 'document.base_shipping_tax_amount',
                'subtotal' => 'document.subtotal', 'base_subtotal' => 'document.base_subtotal',
            ])
            ->where('document.order_id = ?', $order->getId())
            ->where('document.state IS NULL OR document.state <> ?', $canceledState)
            ->order(['document.entity_id', 'item.entity_id']);
        if ($invoiceId !== null) {
            $select->where($invoiceColumn . ' = ?', $invoiceId);
        }
        if ($except !== null) {
            $select->where('document.entity_id <> ?', $except);
        }

        $amounts = [];
        $helper = Mage::helper('weee');
        $store = $order->getStore();
        $empty = ['qty' => 0.0, 'gross' => 0.0, 'base_gross' => 0.0, 'discount' => 0.0, 'base_discount' => 0.0, 'fees' => [], 'has_unlinked' => false];
        $byDocument = [];
        $hasLegacy = false;
        foreach ($connection->fetchAll($select) as $item) {
            $fees = $helper->getAppliedToItem(new Maho\DataObject($item));
            $first = reset($fees);
            $item['legacy'] = $first !== false && empty($first['weee_document_gross_allocated']);
            $hasLegacy = $hasLegacy || $item['legacy'];
            if (empty($first['weee_discount_allocated']) && count($fees) > 1) {
                $fees = $this->recoverHistoricDiscounts($fees, $helper, $store);
            }
            $item['fees'] = $this->feeAmounts($fees);
            $byDocument[$item['document_id']][] = $item;
        }

        $historicLocation = $hasLegacy ? $this->feeWasIncludedInSubtotal($order) : null;
        foreach ($byDocument as $items) {
            $fields = ['gross' => 'weee_tax_applied_row_amount', 'base_gross' => 'base_weee_tax_applied_row_amnt'];
            $scales = ['gross' => 1.0, 'base_gross' => 1.0];
            if ($historicLocation !== null && in_array(true, array_column($items, 'legacy'), true)) {
                $scales = $this->historicGrossScales($items, $store, $historicLocation);
            }
            $running = ['gross' => 0.0, 'base_gross' => 0.0];
            foreach ($items as $item) {
                $id = (int) $item['order_item_id'];
                $amounts[$id] ??= $empty;
                $amounts[$id]['qty'] += (float) $item['qty'];
                $amounts[$id]['has_unlinked'] = $amounts[$id]['has_unlinked'] || !$item['invoice_id'];
                foreach ($fields as $key => $field) {
                    $gross = $this->roundGross($running, $key, (float) $item[$field] * $scales[$key], $store);
                    $amounts[$id][$key] += $gross;
                    $this->allocateTitleGross($item['fees'], $key, $gross, $store);
                }
                foreach ($item['fees'] as $title => $fees) {
                    $amounts[$id]['fees'][$title] ??= ['gross' => 0.0, 'base_gross' => 0.0, 'discount' => 0.0, 'base_discount' => 0.0];
                    foreach (self::AMOUNTS as $key) {
                        $amounts[$id]['fees'][$title][$key] += $fees[$key];
                    }
                    $amounts[$id]['discount'] += $fees['discount'];
                    $amounts[$id]['base_discount'] += $fees['base_discount'];
                }
            }
        }
        return $amounts;
    }

    /**
     * @param array<array-key, array<string, mixed>> $fees
     * @return array<array-key, array<string, mixed>>
     */
    private function recoverHistoricDiscounts(array $fees, Mage_Weee_Helper_Data $helper, Mage_Core_Model_Store $store): array
    {
        $discounts = array_unique(array_filter(array_column($fees, 'weee_discount'), is_numeric(...)), SORT_NUMERIC);
        $baseDiscounts = array_unique(array_filter(array_column($fees, 'base_weee_discount'), is_numeric(...)), SORT_NUMERIC);
        if (count($discounts) > 1 || count($baseDiscounts) > 1) {
            return $fees;
        }

        // Older documents repeated the line discount on every fee.
        foreach ($fees as &$fee) {
            unset($fee['weee_discount'], $fee['base_weee_discount']);
        }
        unset($fee);
        return $helper->allocateTaxDiscounts($fees, (float) reset($discounts), (float) reset($baseDiscounts), $store);
    }

    /**
     * The persisted order identifies which header included the fee when the sale was made.
     * If both headers have residual amounts, the order cannot establish the fee's location.
     */
    public function feeWasIncludedInSubtotal(Mage_Sales_Model_Order $order): ?bool
    {
        $store = $order->getStore();
        $subtotal = (float) $order->getSubtotal();
        $baseSubtotal = (float) $order->getBaseSubtotal();
        $tax = (float) $order->getTaxAmount() - (float) $order->getShippingTaxAmount();
        $baseTax = (float) $order->getBaseTaxAmount() - (float) $order->getBaseShippingTaxAmount();
        foreach ($order->getAllItems() as $item) {
            if ($item->isDummy()) {
                continue;
            }
            $subtotal -= (float) $item->getRowTotal();
            $baseSubtotal -= (float) $item->getBaseRowTotal();
            $tax -= (float) $item->getTaxAmount();
            $baseTax -= (float) $item->getBaseTaxAmount();
        }

        $subtotalHasFee = $store->roundPrice($subtotal) > 0.0 || $store->roundPrice($baseSubtotal) > 0.0;
        $taxHasFee = $store->roundPrice($tax) > 0.0 || $store->roundPrice($baseTax) > 0.0;
        return $subtotalHasFee === $taxHasFee ? null : $subtotalHasFee;
    }

    /**
     * Older documents retained the unrounded item amounts even when their tax or subtotal collector
     * put a rounding remainder on the document. Recover the amount actually charged on that document.
     *
     * @param list<array<string, mixed>> $items
     * @return array{gross: float, base_gross: float}
     */
    private function historicGrossScales(array $items, Mage_Core_Model_Store $store, bool $included): array
    {
        $first = $items[0];
        $scales = [];
        foreach (['gross' => '', 'base_gross' => 'base_'] as $key => $prefix) {
            $discount = 0.0;
            foreach ($items as $item) {
                $discount += array_sum(array_column($item['fees'], $prefix . 'discount'));
            }
            $rowKey = $prefix === '' ? 'weee_tax_applied_row_amount' : 'base_weee_tax_applied_row_amnt';
            $stored = array_sum(array_column($items, $rowKey));
            $subtotalNet = $first[$prefix . 'subtotal'] - array_sum(array_column($items, $prefix . 'row_total'));
            $taxNet = $first['document_' . $prefix . 'tax'] - $first[$prefix . 'shipping_tax'] - array_sum(array_column($items, $prefix . 'tax_amount'));
            $scales[$key] = 1.0;
            $gross = ($included ? $subtotalNet : $taxNet) + $discount;
            $difference = $store->roundPrice($gross) - $store->roundPrice($stored);
            // Other totals cannot explain a large change to a saved fee.
            if ($stored > 0.0 && $gross >= 0.0 && $store->roundPrice(abs($difference)) <= 0.01) {
                $scales[$key] = $gross / $stored;
            }
        }
        return $scales;
    }
}

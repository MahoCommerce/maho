<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

it('keeps fixed tax discounts and the paid amounts across partial documents', function (
    array $invoiceParts,
    array $refundParts,
    bool $linkInvoice,
    string $variant = '',
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $threeFees = in_array($variant, ['distinct small fees', 'same-title small fees'], true);
        $discountedCap = $variant === 'discounted cap';
        $fractionalQty = $variant === 'fractional quantity';
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.72]]);
        $store = setStoreDisplayCurrency($threeFees || $discountedCap ? 'USD' : 'EUR', 'USD,EUR');
        Mage::app()->setCurrentStore($store);
        $includedBefore = in_array($variant, ['subtotal', 'historic subcent to tax', 'invoice switch to tax'], true);
        $invoiceModeChanged = in_array($variant, ['invoice switch to tax', 'invoice switch to subtotal'], true);
        $changedMode = in_array($variant, ['historic mode changed', 'historic subcent to subtotal', 'historic subcent to tax'], true);
        foreach ([
            'tax/weee/enable' => '1',
            'tax/weee/discount' => $variant === 'historic mode changed' ? '0' : '1',
            'tax/weee/include_in_subtotal' => $includedBefore ? '1' : '0',
            'tax/weee/apply_vat' => '0',
            'payment/checkmo/active' => '1',
            'payment/checkmo/allowspecific' => '0',
            'sales_email/invoice/enabled' => '0',
            'sales_email/creditmemo/enabled' => '0',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }

        $connection->update(Mage::getSingleton('core/resource')->getTableName('salesrule/rule'), ['is_active' => 0]);
        $groupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        $rule = Mage::getModel('salesrule/rule')->setName('Partial FPT discount')->setIsActive(true)
            ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)
            ->setDiscountAmount(match (true) {
                $threeFees => 55.0,
                $fractionalQty => 67.0,
                $discountedCap => 90.0,
                default => 10.0,
            });
        if (!$discountedCap) {
            $rule->save();
        }
        $attribute = 'partial_fpt_' . uniqid();
        WeeeDiscountFixture::createFeeAttribute($attribute);
        $secondAttribute = $attribute . '_other';
        $twoFees = in_array($variant, ['two fees', 'two historic fees', 'gross per title', 'duplicated historic fees', 'same title'], true);
        if ($twoFees) {
            WeeeDiscountFixture::createFeeAttribute($secondAttribute);
            if ($variant !== 'same title') {
                $secondFee = Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', $secondAttribute);
                $secondFee->setFrontendLabel('Second fixed tax')->save();
            }
        }
        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, (int) $store->getId())->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@example.test')->setCustomerFirstname('Sample')->setCustomerLastname('Customer')
            ->setCustomerGroupId($groupId);
        $quote->getBillingAddress()->addData([
            'firstname' => 'Sample', 'lastname' => 'Customer', 'email' => 'customer@example.test',
            'street' => '1 Sample Street', 'city' => 'Los Angeles', 'region_id' => 12,
            'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100000',
        ]);
        $cappedInvoice = $variant === 'capped invoice' || $discountedCap;
        $subcent = in_array($variant, ['subcent', 'subcent historic invoices', 'historic subcent to subtotal', 'historic subcent to tax'], true);
        $fees = $variant === 'no fees' ? [] : [$attribute => match (true) {
            $cappedInvoice => 0.008,
            $threeFees => 0.006,
            $fractionalQty => 0.015,
            $subcent => 0.004,
            $variant === 'gross per title' => 0.005,
            $variant === 'historic mode changed' => 3.50,
            default => 0.12,
        }];
        if ($twoFees) {
            $fees[$secondAttribute] = $variant === 'gross per title' ? 0.005 : 0.07;
        }
        if ($threeFees) {
            foreach ([$secondAttribute, $attribute . '_third'] as $code) {
                WeeeDiscountFixture::createFeeAttribute($code);
                if ($variant === 'distinct small fees') {
                    Mage::getModel('catalog/resource_eav_attribute')->loadByCode('catalog_product', $code)
                        ->setFrontendLabel($code)->save();
                }
                $fees[$code] = 0.006;
            }
        }
        $quantities = match (true) {
            $discountedCap => [1, 1, 1, 1, 1],
            $cappedInvoice => [1, 1, 1, 1],
            $invoiceModeChanged => [1, 1],
            $subcent => [1, 1],
            default => [array_sum($invoiceParts)],
        };
        foreach ($quantities as $index => $qty) {
            $withoutFee = $cappedInvoice && $index === ($discountedCap ? 4 : 2);
            $product = partialFptProduct($store, $withoutFee ? [] : $fees, $fractionalQty);
            if ($discountedCap && $index === 2) {
                $rule->loadPost(['actions' => [
                    '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1'],
                    '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $product->getSku()],
                ]])->save();
            }
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
        }
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $cart->collectAndSave($quote);
        $saved = Mage::getModel('sales/service_quote', $quote)->submitOrder();
        $order = Mage::getModel('sales/order')->load((int) $saved->getId());
        $orderItems = $order->getAllVisibleItems();
        if ($cappedInvoice) {
            // Follow the allocator's item ID order for the fee items and invoice the item without a fee last.
            usort($orderItems, static fn(Mage_Sales_Model_Order_Item $left, Mage_Sales_Model_Order_Item $right): int
                => (($right->getBaseWeeeTaxAppliedRowAmnt() > 0) <=> ($left->getBaseWeeeTaxAppliedRowAmnt() > 0))
                    ?: ($left->getId() <=> $right->getId()));
        }
        $orderItem = $orderItems[0];
        if ($variant === 'missing fee fields') {
            $helper = Mage::helper('weee');
            $applied = $helper->getApplied($orderItem);
            unset($applied[0]['amount_incl_tax'], $applied[0]['base_amount_incl_tax']);
            unset($applied[0]['row_amount_incl_tax'], $applied[0]['base_row_amount_incl_tax'], $applied[0]['title']);
            $helper->setApplied($orderItem, $applied);
            $orderItem->save();
        }
        $itemIds = array_map(static fn(Mage_Sales_Model_Order_Item $item): int => (int) $item->getId(), $orderItems);
        $documents = Mage::getService('sales/order_document');
        $invoices = [];
        $creditmemos = [];
        if ($variant === 'canceled invoice') {
            $order->getPayment()->setIsTransactionPending(true);
            $pending = $documents->prepareInvoice($order, [$itemIds[0] => 1]);
            $pending->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE)->register();
            Mage::getModel('core/resource_transaction')->addObject($pending)->addObject($order)->save();
            Mage::getModel('sales/order_invoice_api')->cancel($pending->getIncrementId());
            $canceled = Mage::getModel('sales/order_invoice')->load((int) $pending->getId());
            expect($canceled->isCanceled())->toBeTrue();
            $order = Mage::getModel('sales/order')->load((int) $saved->getId());
        }
        if ($invoiceModeChanged) {
            $store->setConfig('tax/weee/include_in_subtotal', $includedBefore ? '0' : '1');
        }
        $config = Mage::getSingleton('sales/order_invoice_config');
        $totalModels = new ReflectionProperty($config, '_totalModels');
        $originalModels = $config->getTotalModels();
        if ($cappedInvoice || $variant === 'subcent historic invoices' || $changedMode) {
            $totalModels->setValue($config, array_map(
                static fn($model) => $model instanceof Mage_Weee_Model_Total_Invoice_Weee ? new PartialFptHistoricInvoiceCollector($includedBefore) : $model,
                $originalModels,
            ));
        }
        try {
            foreach ($invoiceParts as $index => $qty) {
                if ($cappedInvoice && $index === 2) {
                    $totalModels->setValue($config, $originalModels);
                }
                $invoice = $documents->invoice($order, array_combine($itemIds, (array) $qty), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
                $invoice = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
                if (in_array($variant, ['history', 'two historic fees', 'duplicated historic fees'], true) && $index === 0) {
                    partialFptUseHistoricDocumentFormat($invoice->getAllItems()[0], $orderItem, $variant === 'duplicated historic fees');
                }
                $invoices[] = $invoice;
            }
        } finally {
            $totalModels->setValue($config, $originalModels);
        }
        if ($cappedInvoice) {
            expect(round($invoices[0]->getBaseTaxAmount(), 3))->toBe(0.008)
                ->and(round($invoices[1]->getBaseTaxAmount(), 3))->toBe(0.008);
            $invoice = $invoices[2];
            $creditmemo = $documents->refund(
                $order,
                ['qtys' => array_combine($itemIds, $refundParts[0])],
                offlineRefund: true,
                sendEmail: false,
                invoice: $invoice,
            );
            if ($discountedCap) {
                $discountedItem = array_find($invoice->getAllItems(), static fn($item): bool => (int) $item->getOrderItemId() === $itemIds[2]);
                expect($discountedItem)->toBeInstanceOf(Mage_Sales_Model_Order_Invoice_Item::class);
                $fee = Mage::helper('weee')->getAppliedToItem($discountedItem)[0];
                $creditmemo = Mage::getModel('sales/order_creditmemo')->load((int) $creditmemo->getId());
                expect(round($invoice->getBaseTaxAmount(), 3))->toBe(0.004)
                    ->and(round($invoice->getBaseGrandTotal(), 2))->toBe(1.10)
                    ->and((float) $fee['base_row_amount'])->toBe(0.01)
                    ->and((float) $fee['base_weee_discount'])->toBe(0.01)
                    ->and($creditmemo->getBaseTaxAmount())->toBe(0.0)
                    ->and(round($creditmemo->getBaseGrandTotal(), 2))->toBe(0.10);
                return;
            }
            expect(round($invoice->getBaseTaxAmount(), 3))->toBe(0.004)
                ->and(round($invoice->getBaseGrandTotal(), 2))->toBe(0.90)
                ->and((float) $invoice->getAllItems()[0]->getBaseWeeeTaxAppliedRowAmnt())->toBe(0.0)
                ->and($creditmemo->getBaseGrandTotal())->toBe(0.90);
            return;
        }
        if ($changedMode) {
            $store->setConfig('tax/weee/include_in_subtotal', $includedBefore ? '0' : '1');
            if (!$subcent) {
                expect($invoices[0]->getBaseTaxAmount())->toBe(3.5);
            }
        }
        foreach ($refundParts as $index => $qty) {
            $invoiceIndex = match ($variant) {
                'reverse linked' => count($invoices) - 1 - $index,
                'prior unlinked' => $index - 1,
                default => $index,
            };
            $invoice = $linkInvoice && $invoiceIndex >= 0 ? ($invoices[$invoiceIndex] ?? $invoices[0]) : null;
            $data = ['qtys' => array_combine($itemIds, (array) $qty)];
            if ($variant === 'manual' && $index === 0) {
                $data['adjustment_negative'] = 0.02;
            }
            $checkWarnings = $variant === 'no fees';
            if ($checkWarnings) {
                $errorReporting = error_reporting(error_reporting() | E_WARNING);
                set_error_handler(static function (int $level, string $message, string $file, int $line): bool {
                    if (!(error_reporting() & $level)) {
                        return false;
                    }
                    throw new ErrorException($message, 0, $level, $file, $line);
                }, E_WARNING);
            }
            try {
                $creditmemo = $documents->refund($order, $data, offlineRefund: true, sendEmail: false, invoice: $invoice);
            } finally {
                if ($checkWarnings) {
                    restore_error_handler();
                    error_reporting($errorReporting);
                }
            }
            $creditmemo = Mage::getModel('sales/order_creditmemo')->load((int) $creditmemo->getId());
            if (in_array($variant, ['history', 'two historic fees', 'duplicated historic fees'], true) && $index === 0) {
                partialFptUseHistoricDocumentFormat($creditmemo->getAllItems()[0], $orderItem, $variant === 'duplicated historic fees');
            }
            $creditmemos[] = $creditmemo;
        }
        $order->load((int) $order->getId());

        $cents = static fn(float $amount): int => (int) round($amount * 100);
        if ($subcent) {
            if ($includedBefore) {
                expect($cents($order->getSubtotal()))->toBe(145)
                    ->and($cents($order->getBaseSubtotal()))->toBe(201);
            } else {
                expect($cents($order->getTaxAmount()))->toBe(1)
                    ->and($cents($order->getBaseTaxAmount()))->toBe(1);
            }
        }
        if ($invoiceModeChanged) {
            foreach ($invoices as $invoice) {
                $line = $invoice->getAllItems()[0];
                $centsInSubtotal = $cents($invoice->getBaseSubtotal() - $line->getBaseRowTotal());
                $centsInTax = $cents($invoice->getBaseTaxAmount() - $line->getBaseTaxAmount());
                expect([$centsInSubtotal, $centsInTax])->toBe($includedBefore ? [12, 0] : [0, 11]);
            }
        } elseif ($variant === 'subtotal') {
            expect($cents((float) $orderItem->getDiscountAppliedForWeeeTax()))->toBe(0)
                ->and($cents((float) $orderItem->getBaseDiscountAppliedForWeeeTax()))->toBe(0)
                ->and($cents($orderItem->getBaseDiscountAmount()))->toBe(45);
        } elseif (!$subcent && !in_array($variant, ['gross per title', 'historic mode changed', 'no fees'], true)) {
            expect($cents((float) $orderItem->getDiscountAppliedForWeeeTax()))->toBeGreaterThan(0)
                ->and($cents((float) $orderItem->getBaseDiscountAppliedForWeeeTax()))->toBeGreaterThan(0);
        }
        foreach (['invoices' => $invoices, 'credit memos' => $creditmemos] as $kind => $records) {
            $adjustment = $kind === 'credit memos' && $variant === 'manual' ? $creditmemos[0]->getAdjustmentNegative() : 0.0;
            $baseAdjustment = $kind === 'credit memos' && $variant === 'manual' ? $creditmemos[0]->getBaseAdjustmentNegative() : 0.0;
            expect(array_sum(array_map(static fn($record) => $cents($record->getGrandTotal()), $records)))->toBe($cents($order->getGrandTotal() - $adjustment))
                ->and(array_sum(array_map(static fn($record) => $cents($record->getBaseGrandTotal()), $records)))->toBe($cents($order->getBaseGrandTotal() - $baseAdjustment));
            if ($subcent && !$changedMode) {
                expect(array_sum(array_map(static fn($record) => $cents($record->getTaxAmount()), $records)))->toBe(1)
                    ->and(array_sum(array_map(static fn($record) => $cents($record->getBaseTaxAmount()), $records)))->toBe(1);
            }
            $allocated = $baseAllocated = 0;
            $byTitle = [];
            $baseGrossByTitle = [];
            $grossByTitle = [];
            foreach ($records as $index => $record) {
                $storedFees = Mage::helper('weee')->getApplied($record->getAllItems()[0]);
                if ($threeFees || $fractionalQty) {
                    expect($storedFees)->toHaveCount($threeFees ? 3 : 1)
                        ->and(array_unique(array_column($storedFees, 'title')))->toHaveCount($variant === 'distinct small fees' ? 3 : 1);
                    foreach ($storedFees as $fee) {
                        expect((float) $fee['base_weee_discount'])->toBeLessThanOrEqual((float) $fee['base_row_amount'])
                            ->and((float) $fee['weee_discount'])->toBeLessThanOrEqual((float) $fee['row_amount']);
                    }
                }
                if ($variant === 'duplicated historic fees' && $index === 0) {
                    $first = reset($storedFees);
                    $allocated += $cents((float) $first['weee_discount']);
                    $baseAllocated += $cents((float) $first['base_weee_discount']);
                    continue;
                }
                if ($variant === 'same title' && $index === 0) {
                    expect(array_unique(array_column($storedFees, 'title')))->toHaveCount(1);
                    $baseShares = array_map(static fn(array $fee): array => [
                        $cents((float) $fee['base_amount']),
                        $cents((float) $fee['base_row_amount']),
                        $cents((float) $fee['base_weee_discount']),
                    ], array_values($storedFees));
                    sort($baseShares);
                    expect($baseShares)->toBe([[7, 21, 2], [12, 36, 4]]);
                    $displayShares = array_map(static fn(array $fee): array => [
                        $cents((float) $fee['amount']),
                        $cents((float) $fee['row_amount']),
                        $cents((float) $fee['weee_discount']),
                    ], array_values($storedFees));
                    sort($displayShares);
                    expect($displayShares)->toBe([[5, 15, 1], [9, 26, 3]]);
                }
                foreach ($storedFees as $fee) {
                    $discount = $cents((float) ($fee['weee_discount'] ?? 0.0));
                    $baseDiscount = $cents((float) ($fee['base_weee_discount'] ?? 0.0));
                    $allocated += $discount;
                    $baseAllocated += $baseDiscount;
                    $title = $fee['title'];
                    $byTitle[$title] ??= [0, 0];
                    $byTitle[$title][0] += $discount;
                    $byTitle[$title][1] += $baseDiscount;
                    $baseGrossByTitle[$title] = ($baseGrossByTitle[$title] ?? 0) + $cents((float) $fee['base_row_amount']);
                    $grossByTitle[$title] = ($grossByTitle[$title] ?? 0) + $cents((float) $fee['row_amount']);
                    if ($variant === 'missing fee fields') {
                        expect($title)->toBe('Fixed Product Tax')
                            ->and($fee['amount_incl_tax'])->toBe($fee['amount'])
                            ->and($fee['base_amount_incl_tax'])->toBe($fee['base_amount']);
                    }
                }
            }
            $orderDiscount = array_sum(array_map(static fn($item) => $cents((float) $item->getDiscountAppliedForWeeeTax()), $orderItems));
            $baseOrderDiscount = array_sum(array_map(static fn($item) => $cents((float) $item->getBaseDiscountAppliedForWeeeTax()), $orderItems));
            expect($allocated)->toBe($orderDiscount)->and($baseAllocated)->toBe($baseOrderDiscount);
            if ($twoFees && !in_array($variant, ['duplicated historic fees', 'same title'], true)) {
                $orderFees = Mage::helper('weee')->getApplied($orderItem);
                expect($orderFees)->toHaveCount(2)
                    ->and($byTitle)->toHaveCount(2);
                $runningGross = 0.0;
                $previous = 0;
                foreach ($orderFees as $fee) {
                    expect($byTitle[$fee['title']])->toBe([
                        $cents((float) $fee['weee_discount']),
                        $cents((float) $fee['base_weee_discount']),
                    ]);
                    if ($variant === 'gross per title') {
                        $runningGross += (float) $fee['row_amount'];
                        expect($baseGrossByTitle[$fee['title']])->toBe($cents((float) $fee['base_row_amount']));
                        expect($grossByTitle[$fee['title']])->toBe($cents($runningGross) - $previous);
                        $previous = $cents($runningGross);
                    }
                }
            }
        }
        expect($cents($order->getTotalRefunded()))->toBe($cents($order->getTotalPaid() - $adjustment))
            ->and($cents($order->getBaseTotalRefunded()))->toBe($cents($order->getBaseTotalPaid() - $baseAdjustment));
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'fractional amounts in three documents' => [[1, 1, 2], [1, 1, 2], true],
    'three unit documents' => [[1, 1, 1], [1, 1, 1], true],
    'credit memos against the order' => [[1, 3], [3, 1], false],
    'three credit memos against one invoice' => [[3], [1, 1, 1], true],
    'existing invoices and credit memos' => [[1, 1, 2], [1, 1, 2], true, 'history'],
    'manual refund adjustment' => [[1, 1, 2], [1, 1, 2], true, 'manual'],
    'fixed tax included in subtotal' => [[1, 1, 2], [1, 1, 2], true, 'subtotal'],
    'two fixed taxes with the same title' => [[3, 1], [3, 1], true, 'same title'],
    'refunds the amount charged on a capped intermediate invoice' => [
        [[1, 0, 0, 0], [0, 0, 1, 0], [0, 1, 0, 0], [0, 0, 0, 1]],
        [[0, 1, 0, 0]],
        true,
        'capped invoice',
    ],
    'two distinct fixed taxes' => [[1, 1, 2], [1, 1, 2], true, 'two fees'],
    'two distinct historic fee discounts' => [[1, 1, 2], [1, 1, 2], true, 'two historic fees'],
    'repeated historic fee discount with a missing amount' => [[1, 1, 2], [1, 1, 2], true, 'duplicated historic fees'],
    'historical fees without labels or VAT-inclusive amounts' => [[1, 1, 2], [1, 1, 2], true, 'missing fee fields'],
    'two subcent fees on separate lines and documents' => [[[1, 0], [0, 1]], [[1, 0], [0, 1]], true, 'subcent'],
    'refunds the tax remainder charged on historic invoices' => [[[1, 0], [0, 1]], [[1, 0], [0, 1]], true, 'subcent historic invoices'],
    'refunds historic fixed taxes after changing their subtotal setting' => [[1, 1], [1, 1], true, 'historic mode changed'],
    'refunds subcent historic taxes after switching to subtotal' => [[[1, 0], [0, 1]], [[1, 0], [0, 1]], true, 'historic subcent to subtotal'],
    'refunds subcent historic taxes after switching out of subtotal' => [[[1, 0], [0, 1]], [[1, 0], [0, 1]], true, 'historic subcent to tax'],
    'keeps the gross amount of each FPT title across documents' => [[1, 1], [1, 1], true, 'gross per title'],
    'invoice-linked credit memos in reverse order' => [[1, 1, 2], [2, 1, 1], true, 'reverse linked'],
    'invoice credit memos after an order credit memo' => [[1, 1, 2], [2, 1, 1], true, 'prior unlinked'],
    'ignores a canceled invoice when allocating replacement documents' => [[1, 1, 2], [1, 1, 2], true, 'canceled invoice'],
    'invoices follow the original subtotal after switching fixed tax to tax' => [
        [[1, 0], [0, 1]], [[1, 0], [0, 1]], true, 'invoice switch to tax',
    ],
    'invoices follow the original tax after switching fixed tax to subtotal' => [
        [[1, 0], [0, 1]], [[1, 0], [0, 1]], true, 'invoice switch to subtotal',
    ],
    'ordinary invoice and credit memo without fixed taxes' => [[1], [1], true, 'no fees'],
    'three small fee titles' => [[1, 2], [1, 2], true, 'distinct small fees'],
    'three small fees under one title' => [[1], [1], true, 'same-title small fees'],
    'partial invoices with fractional units' => [[1, 0.5, 0.5], [1, 0.5, 0.5], true, 'fractional quantity'],
    'capped invoice with a discounted fee after historic invoices' => [
        [[1, 0, 0, 0, 0], [0, 1, 0, 0, 0], [0, 0, 1, 1, 0], [0, 0, 0, 0, 1]],
        [[0, 0, 1, 0, 0]],
        true,
        'discounted cap',
    ],
]);

class PartialFptHistoricInvoiceCollector extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    public function __construct(private readonly bool $included) {}

    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        $order = $invoice->getOrder();
        $amount = $baseAmount = 0.0;
        foreach ($invoice->getAllItems() as $item) {
            $gross = $item->getWeeeTaxAppliedAmount() * $item->getQty();
            $baseGross = $item->getBaseWeeeTaxAppliedAmount() * $item->getQty();
            $item->setWeeeTaxAppliedRowAmount($gross)->setBaseWeeeTaxAppliedRowAmount($baseGross);
            $amount += $gross;
            $baseAmount += $baseGross;
        }
        if ($this->included) {
            $tax = min($amount, $order->getSubtotal() - $order->getSubtotalInvoiced() - $invoice->getSubtotal());
            $baseTax = min($baseAmount, $order->getBaseSubtotal() - $order->getBaseSubtotalInvoiced() - $invoice->getBaseSubtotal());
            $invoice->setSubtotal($invoice->getSubtotal() + $tax)->setBaseSubtotal($invoice->getBaseSubtotal() + $baseTax);
        } else {
            $tax = min($amount, $order->getTaxAmount() - $order->getTaxInvoiced() - $invoice->getTaxAmount());
            $baseTax = min($baseAmount, $order->getBaseTaxAmount() - $order->getBaseTaxInvoiced() - $invoice->getBaseTaxAmount());
            $invoice->setTaxAmount($invoice->getTaxAmount() + $tax)->setBaseTaxAmount($invoice->getBaseTaxAmount() + $baseTax);
        }
        $invoice->setGrandTotal($invoice->getGrandTotal() + $tax)->setBaseGrandTotal($invoice->getBaseGrandTotal() + $baseTax);
        return $this;
    }
}

it('preserves payment and refunds when fixed taxes round across invoice lines', function (
    array $batches,
    bool $reverse,
    bool $convert = false,
    bool $allDiscounted = false,
    bool $taxOnGross = false,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        if ($convert) {
            Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $allDiscounted ? 0.72 : 0.50]]);
        }
        $store = setStoreDisplayCurrency($convert ? 'EUR' : 'USD', $convert ? 'USD,EUR' : 'USD');
        Mage::app()->setCurrentStore($store);
        foreach ([
            'tax/weee/enable' => '1',
            'tax/weee/discount' => '1',
            'tax/weee/include_in_subtotal' => '0',
            'tax/weee/apply_vat' => $taxOnGross ? '1' : '0',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT => $taxOnGross ? '0' : '1',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_DISCOUNT_TAX => $taxOnGross ? '1' : '0',
            'payment/checkmo/active' => '1',
            'payment/checkmo/allowspecific' => '0',
            'sales_email/invoice/enabled' => '0',
            'sales_email/creditmemo/enabled' => '0',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }

        $connection->update(Mage::getSingleton('core/resource')->getTableName('salesrule/rule'), ['is_active' => 0]);
        $groupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        $productTaxClassId = 0;
        if ($taxOnGross) {
            // This tax setting changes the collector order cached by the other tests.
            Mage::app()->removeCache('sorted_quote_collectors');
            $name = 'Fixed product tax VAT ' . uniqid();
            $productTaxClassId = (int) Mage::getModel('tax/class')->setClassType('PRODUCT')->setClassName($name)->save()->getId();
            $customerTaxClassId = (int) Mage::getModel('customer/group')->getTaxClassId($groupId);
            $rate = Mage::getService('tax/rate')->save(Mage::getModel('tax/calculation_rate')
                ->setCode($name)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(25.0));
            Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')->setCode($name)
                ->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerTaxClassId])
                ->setTaxProductClass([$productTaxClassId])->setTaxRate([(int) $rate->getId()]));
        }
        $attribute = 'fpt_cap_' . uniqid();
        WeeeDiscountFixture::createFeeAttribute($attribute);
        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, (int) $store->getId())->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@example.test')->setCustomerFirstname('Sample')->setCustomerLastname('Customer')
            ->setCustomerGroupId($groupId);
        $quote->getBillingAddress()->addData([
            'firstname' => 'Sample', 'lastname' => 'Customer', 'email' => 'customer@example.test',
            'street' => '1 Sample Street', 'city' => 'Los Angeles', 'region_id' => 12,
            'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100000',
        ]);
        $skus = [];
        $percentages = $taxOnGross ? [88.0, 84.0, 80.0] : [90.0, $allDiscounted ? 85.0 : 0.0, 80.0];
        foreach ($percentages as $priority => $percent) {
            $product = partialFptProduct($store, [$attribute => 0.008], taxClassId: $productTaxClassId);
            $skus[] = $product->getSku();
            if ($percent > 0.0) {
                Mage::getModel('salesrule/rule')->setName('Fixed product tax discount ' . uniqid())
                    ->setIsActive(true)->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
                    ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
                    ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)->setDiscountAmount($percent)
                    ->setSortOrder($priority)->loadPost(['actions' => [
                        '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1'],
                        '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $product->getSku()],
                    ]])->save();
            }
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
        }
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $cart->collectAndSave($quote);
        $saved = Mage::getModel('sales/service_quote', $quote)->submitOrder();
        $orderId = (int) $saved->getId();
        $order = Mage::getModel('sales/order')->load($orderId);
        $baseAmounts = $allDiscounted ? [0.10, 0.15, 0.20] : [0.10, 1.00, 0.20];
        $baseTaxes = [0.0, 0.0, 0.0];
        if ($taxOnGross) {
            $baseAmounts = [0.15, 0.20, 0.24];
            $baseTaxes = [0.25, 0.25, 0.24];
        }
        $amounts = $convert ? ($allDiscounted ? [0.07, 0.11, 0.15] : [0.05, 0.51, 0.10]) : $baseAmounts;
        $taxes = $convert ? ($allDiscounted ? [0.0, 0.0, 0.01] : [0.0, 0.01, 0.0]) : $baseTaxes;
        $expectedTotal = round(array_sum($amounts), 2);
        $expectedBaseTotal = round(array_sum($baseAmounts), 2);
        expect($order->getGrandTotal())->toBe($expectedTotal)
            ->and($order->getBaseGrandTotal())->toBe($expectedBaseTotal)
            ->and($order->getBaseTaxAmount())->toBe(round(array_sum($baseTaxes), 2));
        $idsBySku = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $idsBySku[$item->getSku()] = (int) $item->getId();
        }
        $itemIds = array_map(static fn(string $sku): int => $idsBySku[$sku], $skus);
        $documents = Mage::getService('sales/order_document');
        $feeHelper = Mage::helper('weee');
        $checkFees = static function (array $items) use ($feeHelper, $taxOnGross): void {
            foreach ($items as $item) {
                foreach ($feeHelper->getAppliedToItem($item) as $fee) {
                    if ($taxOnGross) {
                        expect((float) $fee['weee_discount'])->toBe(0.01)
                            ->and((float) $fee['base_weee_discount'])->toBe(0.01);
                        continue;
                    }
                    expect((float) $fee['weee_discount'])->toBeGreaterThanOrEqual(0.0)
                        ->toBeLessThanOrEqual((float) $fee['row_amount'])
                        ->and((float) $fee['base_weee_discount'])->toBeGreaterThanOrEqual(0.0)
                        ->toBeLessThanOrEqual((float) $fee['base_row_amount']);
                }
            }
        };
        $sumLines = static fn(array $amounts, array $lines): float => round(array_sum(array_intersect_key($amounts, array_fill_keys($lines, true))), 2);
        $invoices = [];
        foreach ($batches as $lines) {
            $quantities = array_fill_keys(array_map(static fn(int $line): int => $itemIds[$line], $lines), 1);
            $order = Mage::getModel('sales/order')->load($orderId);
            $invoice = $documents->invoice($order, $quantities, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
            $invoice = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
            expect($invoice->getGrandTotal())->toBe($sumLines($amounts, $lines))
                ->and($invoice->getBaseGrandTotal())->toBe($sumLines($baseAmounts, $lines))
                ->and($invoice->getTaxAmount())->toBe($sumLines($taxes, $lines))
                ->and($invoice->getBaseTaxAmount())->toBe($sumLines($baseTaxes, $lines));
            $checkFees($invoice->getAllItems());
            $invoices[] = (int) $invoice->getId();
        }

        foreach ($invoices as $index => $invoiceId) {
            $lines = $reverse ? array_reverse($batches[$index]) : $batches[$index];
            foreach ($lines as $line) {
                $order = Mage::getModel('sales/order')->load($orderId);
                $invoice = Mage::getModel('sales/order_invoice')->load($invoiceId);
                $creditmemo = $documents->refund($order, ['qtys' => [$itemIds[$line] => 1]], offlineRefund: true, sendEmail: false, invoice: $invoice);
                $creditmemo = Mage::getModel('sales/order_creditmemo')->load((int) $creditmemo->getId());
                expect($creditmemo->getGrandTotal())->toBe($amounts[$line])
                    ->and($creditmemo->getBaseGrandTotal())->toBe($baseAmounts[$line])
                    ->and($creditmemo->getTaxAmount())->toBe($taxes[$line])
                    ->and($creditmemo->getBaseTaxAmount())->toBe($baseTaxes[$line]);
                $checkFees($creditmemo->getAllItems());
            }
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        expect($order->getTotalPaid())->toBe($expectedTotal)
            ->and($order->getBaseTotalPaid())->toBe($expectedBaseTotal)
            ->and($order->getTotalRefunded())->toBe($expectedTotal)
            ->and($order->getBaseTotalRefunded())->toBe($expectedBaseTotal);
    } finally {
        if ($taxOnGross) {
            Mage::app()->removeCache('sorted_quote_collectors');
        }
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'first discounted and full-price lines in one invoice' => [[[0, 1], [2]], false],
    'refund full-price line before discounted line' => [[[0, 1], [2]], true],
    'later discounted and full-price lines in one invoice' => [[[2, 1], [0]], false],
    'both discounted lines in one invoice' => [[[0, 2], [1]], false],
    'each line in a separate invoice' => [[[1], [0], [2]], false],
    'tax cents differ between EUR sale and USD base' => [[[0, 1], [2]], true, true],
    'three separate rules discount fractional taxes' => [[[0, 1], [2]], false, false, true],
    'three separate rules discount fractional taxes in EUR' => [[[0, 1, 2]], false, true, true],
    'gross discounts on taxable fees in one invoice' => [[[0, 1, 2]], false, false, true, true],
    'gross discounts on taxable fees after a partial invoice' => [[[0, 1], [2]], false, false, true, true],
]);

it('refunds the signed item tax charged on each invoice of a historic order', function (bool $reverse, ?string $unlinkedRefund = null, array $expectedRefunds = [0.18, 0.20]): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('USD', 'USD');
        $store->setConfig('tax/weee/include_in_subtotal', '0');
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-fee-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setIsVirtual(true)->setBaseCurrencyCode('USD')->setOrderCurrencyCode('USD')->setBaseToOrderRate(1.0)
            ->setSubtotal(3.0)->setBaseSubtotal(3.0)->setDiscountAmount(-2.55)->setBaseDiscountAmount(-2.55)
            ->setTaxAmount(-0.07)->setBaseTaxAmount(-0.07)->setGrandTotal(0.38)->setBaseGrandTotal(0.38);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        // Older orders saved the rounded line discount and the unrounded fee separately.
        $fee = ['title' => 'Fixed tax', 'amount' => 0.008, 'base_amount' => 0.008,
            'row_amount' => 0.008, 'base_row_amount' => 0.008,
            'total_weee_discount' => 0.01, 'total_base_weee_discount' => 0.01];
        foreach ([0.90, 0.85, 0.80] as $index => $discount) {
            $item = Mage::getModel('sales/order_item')->setSku('signed-fee-' . $index)->setProductType('virtual')
                ->setQtyOrdered(1)->setPrice(1.0)->setBasePrice(1.0)->setRowTotal(1.0)->setBaseRowTotal(1.0)
                ->setDiscountAmount($discount)->setBaseDiscountAmount($discount)
                ->setWeeeTaxAppliedAmount(0.008)->setBaseWeeeTaxAppliedAmount(0.008)
                ->setWeeeTaxAppliedRowAmount(0.008)->setBaseWeeeTaxAppliedRowAmnt(0.008);
            Mage::helper('weee')->setApplied($item, [$fee]);
            $order->addItem($item);
        }
        $order->save();
        $orderId = (int) $order->getId();
        $ids = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $ids[$item->getSku()] = (int) $item->getId();
        }
        $groups = [[$ids['signed-fee-0'], $ids['signed-fee-1']], [$ids['signed-fee-2']]];
        $service = Mage::getService('sales/order_document');
        $invoices = [];
        foreach ($groups as $index => $group) {
            $order = Mage::getModel('sales/order')->load($orderId);
            $invoice = $service->invoice($order, array_fill_keys($group, 1), captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
            $invoices[$index] = (int) $invoice->getId();
            expect(Mage::getModel('sales/order_invoice')->load($invoice->getId())->getBaseGrandTotal())->toBe([0.18, 0.20][$index]);
        }
        if ($unlinkedRefund === 'adjustment') {
            $order = Mage::getModel('sales/order')->load($orderId);
            $memo = $service->refund($order, ['qtys' => [], 'adjustment_positive' => 0.01], offlineRefund: true, sendEmail: false);
            expect($memo->getInvoiceId())->toBeNull()->and($memo->getAllItems())->toBeEmpty()
                ->and($memo->getBaseGrandTotal())->toBe(0.01);
        }
        foreach ($reverse ? array_reverse($groups, true) : $groups as $index => $group) {
            $refunded = 0.0;
            $lines = $reverse ? array_reverse($group) : $group;
            $refunds = $unlinkedRefund === 'group' && $index === 0 ? [$lines] : array_chunk($lines, 1);
            foreach ($refunds as $items) {
                $order = Mage::getModel('sales/order')->load($orderId);
                $invoice = Mage::getModel('sales/order_invoice')->load($invoices[$index]);
                $data = ['qtys' => array_fill_keys($items, 1)];
                if ($unlinkedRefund === 'adjustment' && $items === [$groups[0][0]]) {
                    $data['adjustment_negative'] = 0.01;
                }
                if (($unlinkedRefund === 'item' && $index === 1) || ($unlinkedRefund === 'group' && $index === 0)) {
                    $invoice = null;
                }
                $memo = $service->refund($order, $data, invoice: $invoice, sendEmail: false);
                if (!$invoice) {
                    expect($memo->getInvoiceId())->toBeNull();
                }
                $refunded += Mage::getModel('sales/order_creditmemo')->load($memo->getId())->getBaseGrandTotal();
            }
            expect(round($refunded, 2))->toBe($expectedRefunds[$index]);
        }
        $order = Mage::getModel('sales/order')->load($orderId);
        expect($order->getBaseTotalPaid())->toBe(0.38)->and($order->getBaseTotalRefunded())->toBe(0.38);
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'invoice order' => [false],
    'reverse invoice order' => [true],
    'order adjustment before invoice refunds' => [true, 'adjustment', [0.17, 0.20]],
    'order item refund belongs to the complete second invoice' => [true, 'item'],
    'order refund covers both items on the first invoice' => [false, 'group'],
]);

it('preserves the order balance for an adjusted refund without an invoice link', function (
    ?float $priorShippingTax,
    float $adjustment,
    float $expectedTotal,
    float $expectedBaseTotal,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        $store->setConfig('tax/weee/include_in_subtotal', '0');
        $store->setConfig('tax/sales_display/shipping', Mage_Tax_Model_Config::DISPLAY_TYPE_EXCLUDING_TAX);
        $order = Mage::getModel('sales/order')->setStoreId((int) $store->getId())
            ->setIncrementId('signed-shipping-fee-' . uniqid())->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
            ->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR')->setBaseToOrderRate(0.72)->setStoreToOrderRate(0.72)
            ->setSubtotal(2.16)->setBaseSubtotal(3.0)->setDiscountAmount(-1.84)->setBaseDiscountAmount(-2.55)
            ->setShippingAmount(2.88)->setBaseShippingAmount(4.0)->setShippingInclTax(3.6)->setBaseShippingInclTax(5.0)
            ->setShippingTaxAmount(0.72)->setBaseShippingTaxAmount(1.0)
            ->setTaxAmount(0.73)->setBaseTaxAmount(0.99)->setGrandTotal(3.93)->setBaseGrandTotal(5.44);
        $order->setPayment(Mage::getModel('sales/order_payment')->setMethod('checkmo'));
        foreach ([[0.65, 0.90, 0.0], [0.61, 0.85, 0.01], [0.58, 0.80, 0.0]] as $index => [$discount, $baseDiscount, $feeDiscount]) {
            $item = Mage::getModel('sales/order_item')->setSku('signed-shipping-fee-' . $index)->setProductType('simple')
                ->setQtyOrdered(1)->setPrice(0.72)->setBasePrice(1.0)->setRowTotal(0.72)->setBaseRowTotal(1.0)
                ->setDiscountAmount($discount)->setBaseDiscountAmount($baseDiscount)
                ->setWeeeTaxAppliedAmount(0.00576)->setBaseWeeeTaxAppliedAmount(0.008)
                ->setWeeeTaxAppliedRowAmount(0.00576)->setBaseWeeeTaxAppliedRowAmnt(0.008);
            Mage::helper('weee')->setApplied($item, [[
                'title' => 'Fixed tax', 'amount' => 0.00576, 'base_amount' => 0.008,
                'row_amount' => 0.00576, 'base_row_amount' => 0.008,
                'total_weee_discount' => $feeDiscount, 'total_base_weee_discount' => 0.01,
            ]]);
            $order->addItem($item);
        }
        $order->save();
        $orderId = (int) $order->getId();
        $loadOrder = static fn(): Mage_Sales_Model_Order => Mage::getModel('sales/order')->load($orderId);
        $ids = array_map(static fn(Mage_Sales_Model_Order_Item $item): int => (int) $item->getId(), $order->getAllVisibleItems());
        $groups = [[$ids[0], $ids[1]], [$ids[2]]];
        $service = Mage::getService('sales/order_document');
        $invoices = [];
        // Keep the signed taxes and totals charged by the older invoice collector.
        foreach ([[0.72, 0.99, 3.78, 5.24], [0.01, -0.01, 0.15, 0.19]] as $index => [$tax, $baseTax, $total, $baseTotal]) {
            $invoice = $service->prepareInvoice($loadOrder(), array_fill_keys($groups[$index], 1));
            $invoice->setTaxAmount($tax)->setBaseTaxAmount($baseTax)->setGrandTotal($total)->setBaseGrandTotal($baseTotal)
                ->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE)->register();
            Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();
            $invoices[] = $invoice;
        }
        $order = $loadOrder();
        expect($order->getTotalPaid())->toBe(3.93)->and($order->getBaseTotalPaid())->toBe(5.43);
        if ($priorShippingTax !== null) {
            $prior = $service->refund($order, ['qtys' => [], 'shipping_amount' => 4.0], invoice: $invoices[0], beforeRegister: static function (
                Mage_Sales_Model_Order_Creditmemo $memo,
            ) use ($priorShippingTax): void {
                // An earlier collector used the signed order tax cap for a shipping-only credit.
                $memo->setBaseTaxAmount($priorShippingTax)->setBaseGrandTotal(4.0 + $priorShippingTax);
            });
            expect($prior->getAllItems())->toBeEmpty()
                ->and($prior->getBaseShippingTaxAmount())->toBe(1.0)
                ->and($prior->getBaseGrandTotal())->toBe(4.98);
        }
        $items = $priorShippingTax === null ? $groups[1] : $groups[0];
        $shipping = $priorShippingTax === null && $adjustment > 0 ? 4.0 : 0.0;
        $data = ['qtys' => array_fill_keys($items, 1), 'shipping_amount' => $shipping];
        $data[$adjustment > 0 ? 'adjustment_positive' : 'adjustment_negative'] = abs($adjustment);
        $order = $loadOrder();
        $memo = $service->refund($order, $data, offlineRefund: true, sendEmail: false);
        $memo = Mage::getModel('sales/order_creditmemo')->load((int) $memo->getId());
        expect($memo->getInvoiceId())->toBeNull()
            ->and($memo->getGrandTotal())->toBe($expectedTotal)
            ->and($memo->getBaseGrandTotal())->toBe($expectedBaseTotal)
            ->and($memo->getBaseShippingAmount())->toBe($shipping)
            ->and($loadOrder()->getBaseTotalRefunded())->toBe(round($expectedBaseTotal + ($priorShippingTax === null ? 0.0 : 4.98), 2));
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'shipping and a positive adjustment use the available balance' => [null, 0.25, 3.93, 5.43],
    'positive adjustment after an earlier signed shipping credit' => [0.98, 0.20, 0.32, 0.45],
    'a negative adjustment keeps the order tax cap' => [null, -0.01, 0.14, 0.17],
]);


function partialFptProduct(
    Mage_Core_Model_Store $store,
    array $fees,
    bool $fractionalQty = false,
    int $taxClassId = 0,
): Mage_Catalog_Model_Product {
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    $product->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
        ->setSku('partial-fpt-' . uniqid())->setName('Partial FPT item')->setPrice(1.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId($taxClassId)->setWebsiteIds([(int) $store->getWebsiteId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20, 'is_qty_decimal' => $fractionalQty]);
    foreach ($fees as $attribute => $price) {
        $product->setData($attribute, [['website_id' => (int) $store->getWebsiteId(), 'country' => 'US', 'state' => '*', 'price' => $price]]);
    }
    $product->save();
    return Mage::getModel('catalog/product')->setStoreId($store->getId())->load($product->getId());
}

function partialFptUseHistoricDocumentFormat(
    Mage_Sales_Model_Order_Invoice_Item|Mage_Sales_Model_Order_Creditmemo_Item $item,
    Mage_Sales_Model_Order_Item $orderItem,
    bool $duplicate = false,
): void {
    $helper = Mage::helper('weee');
    $fees = $helper->getApplied($item);
    $discount = array_sum(array_column($fees, 'weee_discount'));
    $baseDiscount = array_sum(array_column($fees, 'base_weee_discount'));
    foreach ($fees as $index => &$fee) {
        unset($fee['weee_discount_allocated'], $fee['weee_document_gross_allocated']);
        $fee['total_weee_discount'] = $orderItem->getDiscountAppliedForWeeeTax();
        $fee['total_base_weee_discount'] = $orderItem->getBaseDiscountAppliedForWeeeTax();
        if ($duplicate) {
            $fee['weee_discount'] = $discount;
            $fee['base_weee_discount'] = $baseDiscount;
            if ($index === array_key_last($fees)) {
                unset($fee['base_weee_discount']);
            }
        }
    }
    unset($fee);
    $helper->setApplied($item, $fees);
    $item->save();
}

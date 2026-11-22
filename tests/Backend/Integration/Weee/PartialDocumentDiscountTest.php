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
    set_error_handler(static function (int $level, string $message, string $file, int $line): never {
        throw new ErrorException($message, 0, $level, $file, $line);
    }, E_WARNING);
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.72]]);
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
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
        Mage::getModel('salesrule/rule')->setName('Partial FPT discount')->setIsActive(true)
            ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)->setDiscountAmount(10.0)->save();
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
        $cappedInvoice = $variant === 'capped invoice';
        $subcent = in_array($variant, ['subcent', 'subcent historic invoices', 'historic subcent to subtotal', 'historic subcent to tax'], true);
        $fees = $variant === 'no fees' ? [] : [$attribute => match (true) {
            $cappedInvoice => 0.008,
            $subcent => 0.004,
            $variant === 'gross per title' => 0.005,
            $variant === 'historic mode changed' => 3.50,
            default => 0.12,
        }];
        if ($twoFees) {
            $fees[$secondAttribute] = $variant === 'gross per title' ? 0.005 : 0.07;
        }
        $quantities = match (true) {
            $cappedInvoice => [1, 1, 1, 1],
            $invoiceModeChanged => [1, 1],
            $subcent => [1, 1],
            default => [array_sum($invoiceParts)],
        };
        foreach ($quantities as $index => $qty) {
            $product = partialFptProduct($store, $cappedInvoice && $index === 3 ? [] : $fees);
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
        }
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $cart->collectAndSave($quote);
        $saved = Mage::getModel('sales/service_quote', $quote)->submitOrder();
        $order = Mage::getModel('sales/order')->load((int) $saved->getId());
        $orderItems = $order->getAllVisibleItems();
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
            $invoice = $invoices[2];
            $creditmemo = $documents->refund(
                $order,
                ['qtys' => array_combine($itemIds, $refundParts[0])],
                offlineRefund: true,
                sendEmail: false,
                invoice: $invoice,
            );
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
            $creditmemo = $documents->refund($order, $data, offlineRefund: true, sendEmail: false, invoice: $invoice);
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
        restore_error_handler();
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

function partialFptProduct(Mage_Core_Model_Store $store, array $fees): Mage_Catalog_Model_Product
{
    $product = Mage::getModel('catalog/product')->setStoreId(0);
    $product->setAttributeSetId($product->getDefaultAttributeSetId())
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
        ->setSku('partial-fpt-' . uniqid())->setName('Partial FPT item')->setPrice(1.0)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([(int) $store->getWebsiteId()])
        ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20]);
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

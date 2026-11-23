<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Weee
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoBackendTestCase::class);

it('keeps a discount on taxable fixed product tax on the line that earned it', function (
    bool $discountedFirst,
    float $taxPercent,
    float $discountPercent,
    float $discountedTotal,
    float $feeDiscount,
    bool $changeFeeLocation = false,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $store = setStoreDisplayCurrency('USD', 'USD');
        Mage::app()->setCurrentStore($store);
        foreach ([
            'tax/weee/enable' => '1',
            'tax/weee/discount' => '1',
            'tax/weee/include_in_subtotal' => '0',
            'tax/weee/apply_vat' => '1',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_PRICE_INCLUDES_TAX => '1',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_APPLY_AFTER_DISCOUNT => '0',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_DISCOUNT_TAX => '1',
            Mage_Tax_Model_Config::CONFIG_XML_PATH_DEFAULT_COUNTRY => 'US',
            Mage_Tax_Model_Config::XML_PATH_ALGORITHM => Mage_Tax_Model_Calculation::CALC_TOTAL_BASE,
            'payment/checkmo/active' => '1',
            'payment/checkmo/allowspecific' => '0',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }
        Mage::app()->removeCache('sorted_quote_collectors');

        $name = 'Signed fixed product tax ' . uniqid();
        $productTaxClass = Mage::getModel('tax/class')->setClassType('PRODUCT')->setClassName($name)->save();
        $customerTaxClassId = (int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        $group = Mage::getModel('customer/group')->setCode('FPT ' . uniqid())->setTaxClassId($customerTaxClassId)->save();
        $rate = Mage::getService('tax/rate')->save(Mage::getModel('tax/calculation_rate')
            ->setCode($name)->setTaxCountryId('US')->setTaxPostcode('*')->setRate($taxPercent));
        Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')->setCode($name)
            ->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerTaxClassId])
            ->setTaxProductClass([(int) $productTaxClass->getId()])->setTaxRate([(int) $rate->getId()]));

        $feeAttribute = 'signed_fpt_' . uniqid();
        WeeeDiscountFixture::createFeeAttribute($feeAttribute);
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, $storeId)->setCustomerIsGuest(true)->setCustomerGroupId((int) $group->getId())
            ->setCustomerEmail('customer@example.test')->setCustomerFirstname('Sample')->setCustomerLastname('Customer');
        $quote->getBillingAddress()->addData([
            'firstname' => 'Sample', 'lastname' => 'Customer', 'email' => 'customer@example.test',
            'street' => '1 Sample Street', 'city' => 'Los Angeles', 'region_id' => 12,
            'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100000',
        ]);

        $skus = [];
        $fullPriceTotal = $changeFeeLocation ? 100.0 : 110.0;
        foreach ([100.0 + $taxPercent, 100.0] as $index => $price) {
            $product = Mage::getModel('catalog/product')->setStoreId(0);
            $product->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
                ->setSku('signed-fee-' . uniqid())->setName('Fixed tax product')->setPrice($price)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setTaxClassId($index === 0 ? (int) $productTaxClass->getId() : 0)
                ->setWebsiteIds([$websiteId])
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20])
                ->setData($feeAttribute, !$changeFeeLocation || $index === 0 ? [['website_id' => $websiteId, 'country' => 'US', 'state' => '*', 'price' => 10.0]] : [])
                ->save();
            $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId());
            $skus[] = $product->getSku();
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => 1]), save: false);
        }
        Mage::getModel('salesrule/rule')->setName($name)->setIsActive(true)
            ->setWebsiteIds([$websiteId])->setCustomerGroupIds([(int) $group->getId()])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)->setDiscountAmount($discountPercent)
            ->loadPost(['actions' => [
                '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1'],
                '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $skus[0]],
            ]])->save();
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $cart->collectAndSave($quote);
        $saved = Mage::getModel('sales/service_quote', $quote)->submitOrder();
        $orderId = (int) $saved->getId();
        $order = Mage::getModel('sales/order')->load($orderId);
        $idsBySku = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $idsBySku[$item->getSku()] = (int) $item->getId();
        }
        $itemIds = array_map(static fn(string $sku): int => $idsBySku[$sku], $skus);
        $lines = $discountedFirst ? [0, 1] : [1, 0];
        $documents = Mage::getService('sales/order_document');
        $invoices = [];
        foreach ($lines as $line) {
            $order = Mage::getModel('sales/order')->load($orderId);
            $invoice = $documents->invoice(
                $order,
                [$itemIds[$line] => 1],
                captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
                sendEmail: false,
            );
            $invoices[$line] = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
        }

        if ($changeFeeLocation) {
            $store->setConfig('tax/weee/include_in_subtotal', '1');
        }
        $refunds = [];
        foreach ($lines as $line) {
            $order = Mage::getModel('sales/order')->load($orderId);
            $creditmemo = $documents->refund(
                $order,
                ['qtys' => [$itemIds[$line] => 1]],
                offlineRefund: true,
                sendEmail: false,
                invoice: $invoices[$line],
            );
            $refunds[$line] = Mage::getModel('sales/order_creditmemo')->load((int) $creditmemo->getId());
        }

        $order = Mage::getModel('sales/order')->load($orderId);
        $expectedTotal = $discountedTotal + $fullPriceTotal;
        if ($changeFeeLocation) {
            $line = $refunds[0]->getAllItems()[0];
            expect($refunds[0]->getSubtotal())->toBe($line->getRowTotal())
                ->and($refunds[0]->getTaxAmount())->toEqualWithDelta($line->getTaxAmount() + 10.0 - $feeDiscount, 0.000001)
                ->and($refunds[0]->getBaseSubtotal())->toBe($line->getBaseRowTotal())
                ->and($refunds[0]->getBaseTaxAmount())->toEqualWithDelta($line->getBaseTaxAmount() + 10.0 - $feeDiscount, 0.000001)
                ->and($order->getTotalRefunded())->toEqualWithDelta($expectedTotal, 0.000001);
            return;
        }

        expect($order->getGrandTotal())->toEqualWithDelta($expectedTotal, 0.000001)
            ->and($order->getTotalPaid())->toEqualWithDelta($expectedTotal, 0.000001)
            ->and($order->getTotalRefunded())->toEqualWithDelta($expectedTotal, 0.000001);
        foreach ([$discountedTotal, $fullPriceTotal] as $line => $total) {
            foreach ([$invoices[$line], $refunds[$line]] as $document) {
                expect($document->getGrandTotal())->toEqualWithDelta($total, 0.000001)
                    ->and($document->getBaseGrandTotal())->toEqualWithDelta($total, 0.000001);
                $fee = Mage::helper('weee')->getAppliedToItem($document->getAllItems()[0])[0];
                $discount = $line === 0 ? $feeDiscount : 0.0;
                expect((float) $fee['row_amount'])->toBe(10.0)
                    ->and((float) $fee['base_row_amount'])->toBe(10.0)
                    ->and((float) $fee['weee_discount'])->toBe($discount)
                    ->and((float) $fee['base_weee_discount'])->toBe($discount);
            }
        }
    } finally {
        Mage::app()->removeCache('sorted_quote_collectors');
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'discounted line billed first' => [true, 20.0, 90.0, 13.20, 10.80],
    'full-price line billed first' => [false, 20.0, 90.0, 13.20, 10.80],
    'one cent of signed fixed product tax' => [true, 10.0, 91.0, 10.89, 10.01],
    'signed fixed tax remains in tax after switching to subtotal' => [true, 10.0, 91.0, 10.89, 10.01, true],
]);

it('reads the tax-inclusive amount of each saved fee from its row or unit amount', function (): void {
    $order = Mage::getModel('sales/order')->setStoreId(1);
    foreach ([1 => [8.64, 12.96], 2 => [0.0, 0.0]] as $id => [$recyclingDiscount, $disposalDiscount]) {
        $item = Mage::getModel('sales/order_item')->setId($id)->setQtyOrdered(2);
        Mage::helper('weee')->setApplied($item, [
            [
                'title' => 'Recycling', 'row_amount' => 8.0, 'base_row_amount' => 8.0,
                'row_amount_incl_tax' => 9.60, 'base_amount_incl_tax' => 4.80,
                'weee_discount' => $recyclingDiscount, 'base_weee_discount' => $recyclingDiscount,
            ],
            [
                'title' => 'Disposal', 'row_amount' => 12.0, 'base_row_amount' => 12.0,
                'row_amount_incl_tax' => 14.40, 'base_row_amount_incl_tax' => 14.40,
                'weee_discount' => $disposalDiscount, 'base_weee_discount' => $disposalDiscount,
            ],
        ]);
        $order->getItemsCollection()->addItem($item);
    }

    $amounts = new Mage_Weee_Model_DocumentAllocation()->orderAmounts($order);
    expect(array_column($amounts, 'gross'))->toBe([20.0, 20.0])
        ->and(array_column($amounts, 'base_gross'))->toBe([20.0, 20.0]);
});

it('retains aggregate-only legacy fee amounts when collecting tax-inclusive totals', function (): void {
    $orderItem = Mage::getModel('sales/order_item')->setQtyOrdered(2);
    $item = Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem)->setQty(1);
    $store = Mage::app()->getStore();
    $share = ['gross' => 0.09, 'base_gross' => 0.12, 'discount' => 0.0, 'base_discount' => 0.0, 'fees' => []];

    $allocation = new Mage_Weee_Model_DocumentAllocation();
    $allocation->apply($item, $share, $store);

    expect($allocation->getTaxInclusiveAmounts($item))->toBe([0.09, 0.12])
        ->and($item->getWeeeTaxAppliedRowAmount())->toBe(0.09)
        ->and($item->getBaseWeeeTaxAppliedRowAmount())->toBe(0.12)
        ->and(Mage::helper('weee')->getApplied($item))->toBe([]);
});

it('uses the rewritten fee row getters for document tax-inclusive totals', function (): void {
    $item = Mage::getModel('sales/order_creditmemo_item')->setData('qty', 2);
    $helper = Mage::helper('weee');
    $helper->setApplied($item, [['row_amount_incl_tax' => 0.06, 'base_row_amount_incl_tax' => 0.10]]);
    Mage::unregister('_helper/weee');
    Mage::register('_helper/weee', new class extends Mage_Weee_Helper_Data {
        #[\Override]
        public function getRowWeeeTaxInclTax($item)
        {
            return parent::getRowWeeeTaxInclTax($item) + 0.02;
        }

        #[\Override]
        public function getBaseRowWeeeTaxInclTax($item)
        {
            return parent::getBaseRowWeeeTaxInclTax($item) + 0.03;
        }

        #[\Override]
        public function getWeeeTaxInclTax($item)
        {
            return 1.0;
        }

        #[\Override]
        public function getBaseWeeeTaxInclTax($item)
        {
            return 2.0;
        }
    });
    try {
        expect((new Mage_Weee_Model_DocumentAllocation())->getTaxInclusiveAmounts($item))->toBe([0.08, 0.13]);
    } finally {
        Mage::unregister('_helper/weee');
        Mage::register('_helper/weee', $helper);
    }
});

it('accumulates subcent discounts before rounding a historic fee with a missing title', function (): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        $number = uniqid('historic-fee-');
        $order = Mage::getModel('sales/order')->setStoreId(1)
            ->setIncrementId($number)->setBaseCurrencyCode('USD')->setOrderCurrencyCode('EUR');
        $orderItem = Mage::getModel('sales/order_item')->setProductType('simple')->setSku($number)
            ->setQtyOrdered(3)->setQtyInvoiced(3)->setWeeeTaxAppliedRowAmount(6.0)->setBaseWeeeTaxAppliedRowAmnt(3.0);
        $helper = Mage::helper('weee');
        $historicFees = [
            ['title' => 'Recycling', 'row_amount' => 0.80, 'base_row_amount' => 0.40, 'weee_discount' => 0.008, 'base_weee_discount' => 0.004],
            ['row_amount' => 1.20, 'base_row_amount' => 0.60, 'weee_discount' => 0.012, 'base_weee_discount' => 0.006],
        ];
        $originalFees = $historicFees;
        $originalFees[1]['title'] = 'Disposal';
        foreach ($originalFees as &$fee) {
            $fee['row_amount'] *= 3;
            $fee['base_row_amount'] *= 3;
        }
        unset($fee);
        $helper->setApplied($orderItem, $originalFees);
        $order->addItem($orderItem)->save();
        for ($number = 1; $number <= 3; $number++) {
            $invoice = Mage::getModel('sales/order_invoice')->setOrder($order)->setIncrementId($order->getIncrementId() . '-' . $number)
                ->setState(Mage_Sales_Model_Order_Invoice::STATE_PAID)->setTaxAmount(1.98)->setBaseTaxAmount(0.99);
            $item = Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem)->setData('qty', 1)
                ->setWeeeTaxAppliedRowAmount(2.0)->setBaseWeeeTaxAppliedRowAmount(1.0);
            $helper->setApplied($item, $historicFees);
            $invoice->addItem($item)->save();
        }

        $order = Mage::getModel('sales/order')->load((int) $order->getId());
        $orderItem = $order->getItemById((int) $orderItem->getId());
        $memo = Mage::getModel('sales/order_creditmemo')->setOrder($order);
        $memoItem = Mage::getModel('sales/order_creditmemo_item')->setOrderItem($orderItem)->setQty(3);
        $memo->addItem($memoItem);
        Mage::getModel('weee/total_creditmemo_weee')->collect($memo);

        $fees = $helper->getApplied($memoItem);
        expect(array_sum(array_column($fees, 'weee_discount')))->toEqualWithDelta(0.06, 0.000001)
            ->and(array_sum(array_column($fees, 'base_weee_discount')))->toEqualWithDelta(0.03, 0.000001)
            ->and($memo->getGrandTotal())->toEqualWithDelta(5.94, 0.000001)
            ->and($memo->getBaseGrandTotal())->toEqualWithDelta(2.97, 0.000001);
    } finally {
        $connection->rollBack();
    }
});

it('keeps unit-only WEEE rewrites in invoice and credit memo inclusive subtotals', function (float $unit, float $baseUnit): void {
    $order = Mage::getModel('sales/order')->setStoreId(1)->setTaxAmount(0.09)->setBaseTaxAmount(0.15);
    $orderItem = Mage::getModel('sales/order_item')->setId(1)->setProductType('simple')
        ->setQtyOrdered(3)->setQtyInvoiced(3)->setWeeeTaxAppliedRowAmount(0.09)->setBaseWeeeTaxAppliedRowAmnt(0.15);
    $order->getItemsCollection()->addItem($orderItem);
    $helper = Mage::helper('weee');
    $helper->setApplied($orderItem, [['title' => 'Recycling', 'amount' => $unit, 'base_amount' => $baseUnit, 'row_amount' => 0.09, 'base_row_amount' => 0.15]]);
    $invoice = Mage::getModel('sales/order_invoice')->setOrder($order)->setSubtotalInclTax(32.0)->setBaseSubtotalInclTax(40.0);
    $invoice->addItem(Mage::getModel('sales/order_invoice_item')->setOrderItem($orderItem)->setData('qty', 2));
    $memo = Mage::getModel('sales/order_creditmemo')->setOrder($order)->setSubtotalInclTax(32.0)->setBaseSubtotalInclTax(40.0);
    $memo->addItem(Mage::getModel('sales/order_creditmemo_item')->setOrderItem($orderItem)->setData('qty', 2));
    Mage::unregister('_helper/weee');
    Mage::register('_helper/weee', new class extends Mage_Weee_Helper_Data {
        #[\Override]
        public function getWeeeTaxInclTax($item)
        {
            return parent::getWeeeTaxInclTax($item) + 0.01;
        }

        #[\Override]
        public function getBaseWeeeTaxInclTax($item)
        {
            return parent::getBaseWeeeTaxInclTax($item) + 0.02;
        }
    });
    try {
        Mage::getModel('weee/total_invoice_weee')->collect($invoice);
        Mage::getModel('weee/total_creditmemo_weee')->collect($memo);

        expect($invoice->getSubtotalInclTax())->toEqualWithDelta(32.08, 0.000001)
            ->and($invoice->getBaseSubtotalInclTax())->toEqualWithDelta(40.14, 0.000001)
            ->and($memo->getSubtotalInclTax())->toEqualWithDelta(32.08, 0.000001)
            ->and($memo->getBaseSubtotalInclTax())->toEqualWithDelta(40.14, 0.000001);
    } finally {
        Mage::unregister('_helper/weee');
        Mage::register('_helper/weee', $helper);
    }
})->with([
    'allocated row equals nominal unit times quantity' => [0.03, 0.05],
    'allocated row differs from nominal unit times quantity' => [0.029, 0.049],
]);

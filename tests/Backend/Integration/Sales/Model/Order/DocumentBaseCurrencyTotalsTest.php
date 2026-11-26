<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps document amounts in the base currency after the order currency amount rounds to zero', function (
    bool $useCoupon,
    array $expected,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => 0.1]]);
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        Mage::app()->setCurrentStore($store);
        foreach ([
            'tax/weee/enable' => '0',
            'tax/calculation/based_on' => 'billing',
            'tax/calculation/price_includes_tax' => '0',
            'tax/calculation/algorithm' => Mage_Tax_Model_Calculation::CALC_ROW_BASE,
            'payment/checkmo/active' => '1',
            'payment/checkmo/allowspecific' => '0',
            'sales_email/order/enabled' => '0',
            'sales_email/invoice/enabled' => '0',
            'sales_email/creditmemo/enabled' => '0',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }

        $connection->update(Mage::getSingleton('core/resource')->getTableName('salesrule/rule'), ['is_active' => 0]);
        $fixture = 'Document currency ' . uniqid();
        $groupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        $taxClassId = 0;
        if (!$useCoupon) {
            $customerClassId = (int) Mage::getModel('customer/group')->getTaxClassId($groupId);
            $productClass = Mage::getModel('tax/class')->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
                ->setClassName($fixture)->save();
            $taxClassId = (int) $productClass->getId();
            $rate = Mage::getService('tax/rate')->save(Mage::getModel('tax/calculation_rate')
                ->setCode($fixture)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(20.0));
            Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')
                ->setCode($fixture)->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerClassId])
                ->setTaxProductClass([$taxClassId])->setTaxRate([(int) $rate->getId()]));
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

        $firstSku = 'document-currency-' . uniqid();
        foreach ([[0.10, 2, $taxClassId, $firstSku], [1.00, 1, 0, 'document-currency-' . uniqid()]] as [$price, $qty, $classId, $sku]) {
            $product = Mage::getModel('catalog/product')->setStoreId(0);
            $product->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
                ->setSku($sku)->setName($fixture)->setPrice($price)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setTaxClassId($classId)->setWebsiteIds([(int) $store->getWebsiteId()])
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20])
                ->save();
            $product = Mage::getModel('catalog/product')->setStoreId($store->getId())->load($product->getId());
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
        }

        if ($useCoupon) {
            $coupon = 'BASE-ONLY-' . uniqid();
            $rule = Mage::getModel('salesrule/rule')->setName($fixture)->setIsActive(true)
                ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
                ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)->setCouponCode($coupon)
                ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_FIXED_ACTION)->setDiscountAmount(0.01);
            $rule->loadPost(['actions' => [
                '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1'],
                '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => $firstSku],
            ]])->save();
            $quote->setCouponCode($coupon);
        }
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $cart->collectAndSave($quote);
        $placed = Mage::getModel('sales/service_quote', $quote)->submitOrder();
        $order = Mage::getModel('sales/order')->load((int) $placed->getId());
        $orderItem = $order->getAllVisibleItems()[0];
        $itemId = (int) $orderItem->getId();

        $documents = Mage::getService('sales/order_document');
        $invoice = $documents->invoice($order, [$itemId => 2], captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
        $invoice = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
        $order = Mage::getModel('sales/order')->load((int) $order->getId());
        $orderItem = $order->getItemById($itemId);
        $invoiceItem = $invoice->getAllItems()[0];

        $memo = $documents->refund($order, ['qtys' => [$itemId => 1]], offlineRefund: true, sendEmail: false, invoice: $invoice);
        $memo = Mage::getModel('sales/order_creditmemo')->load((int) $memo->getId());
        $memoItem = $memo->getAllItems()[0];
        $updatedOrder = Mage::getModel('sales/order')->load((int) $order->getId());
        $updatedItem = $updatedOrder->getItemById($itemId);

        if ($useCoupon) {
            expect($order->getCouponCode())->toBe($coupon);
        }
        expect([$order->getOrderCurrencyCode(), $order->getBaseCurrencyCode(), $order->getBaseToOrderRate()])
            ->toBe(['EUR', 'USD', 0.1]);
        $prefix = $useCoupon ? 'discount' : 'tax';
        $amounts = static fn(Maho\DataObject $row, string $field): array => [
            (float) $row->getData($field),
            (float) $row->getData('base_' . $field),
        ];
        $amountField = $prefix . '_amount';
        $invoicedField = $prefix . '_invoiced';
        $refundedField = $prefix . '_refunded';
        expect([
            'order' => [$amounts($order, $amountField), $amounts($orderItem, $amountField), $amounts($order, 'grand_total')],
            'invoice' => [$amounts($invoice, $amountField), $amounts($invoiceItem, $amountField), $amounts($invoice, 'grand_total')],
            'invoiced' => [$amounts($order, $invoicedField), $amounts($orderItem, $invoicedField)],
            'refund' => [$amounts($memo, $amountField), $amounts($memoItem, $amountField), $amounts($memo, 'grand_total')],
            'refunded' => [$amounts($updatedOrder, $refundedField), $amounts($updatedItem, $refundedField)],
        ])->toBe($expected);
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'tax' => [false, [
        'order' => [[0.0, 0.04], [0.0, 0.04], [0.12, 1.24]],
        'invoice' => [[0.0, 0.04], [0.0, 0.04], [0.02, 0.24]],
        'invoiced' => [[0.0, 0.04], [0.0, 0.04]],
        'refund' => [[0.0, 0.02], [0.0, 0.02], [0.01, 0.12]],
        'refunded' => [[0.0, 0.02], [0.0, 0.02]],
    ]],
    'fixed coupon' => [true, [
        'order' => [[0.0, -0.02], [0.0, 0.02], [0.12, 1.18]],
        'invoice' => [[0.0, -0.02], [0.0, 0.02], [0.02, 0.18]],
        'invoiced' => [[0.0, -0.02], [0.0, 0.02]],
        'refund' => [[0.0, -0.01], [0.0, 0.01], [0.01, 0.09]],
        'refunded' => [[0.0, -0.01], [0.0, 0.01]],
    ]],
]);

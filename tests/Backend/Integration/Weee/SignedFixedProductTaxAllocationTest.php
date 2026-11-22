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
                ->setData($feeAttribute, [['website_id' => $websiteId, 'country' => 'US', 'state' => '*', 'price' => 10.0]])
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
        expect($order->getGrandTotal())->toEqualWithDelta($discountedTotal + 110.0, 0.000001)
            ->and($order->getTotalPaid())->toEqualWithDelta($discountedTotal + 110.0, 0.000001)
            ->and($order->getTotalRefunded())->toEqualWithDelta($discountedTotal + 110.0, 0.000001);
        foreach ([$discountedTotal, 110.00] as $line => $total) {
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
]);

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the shipping coupon on the invoice that charges shipping', function (
    float $rate,
    float $shipping,
    bool $cancelFirst = false,
    bool $discountItems = false,
    bool $taxShipping = false,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $rate]]);
        $store = setStoreDisplayCurrency('EUR', 'USD,EUR');
        Mage::app()->setCurrentStore($store);
        foreach ([
            'tax/weee/enable' => '0',
            'tax/classes/shipping_tax_class' => '0',
            'tax/calculation/price_includes_tax' => '0',
            'tax/calculation/shipping_includes_tax' => '0',
            'carriers/flatrate/active' => '1',
            'carriers/flatrate/sallowspecific' => '0',
            'carriers/flatrate/price' => (string) $shipping,
            'carriers/flatrate/type' => 'O',
            'payment/checkmo/active' => '1',
            'payment/checkmo/allowspecific' => '0',
            'sales_email/order/enabled' => '0',
            'sales_email/invoice/enabled' => '0',
        ] as $path => $value) {
            $store->setConfig($path, $value);
        }

        $connection->update(Mage::getSingleton('core/resource')->getTableName('salesrule/rule'), ['is_active' => 0]);
        $groupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        $coupon = 'SHIPPING-ONLY-' . uniqid();
        $rule = Mage::getModel('salesrule/rule')->setName('One cent off shipping')->setIsActive(true)
            ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)->setCouponCode($coupon)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_FIXED_ACTION)->setDiscountAmount(0.01)->setApplyToShipping(true);
        $rule->loadPost(['actions' => [
            '1' => ['type' => 'salesrule/rule_condition_product_combine', 'aggregator' => 'all', 'value' => '1'],
            '1--1' => ['type' => 'salesrule/rule_condition_product', 'attribute' => 'sku', 'operator' => '==', 'value' => 'not-in-this-cart'],
        ]])->save();
        if ($discountItems) {
            Mage::getModel('salesrule/rule')->setName('Half off the items')->setIsActive(true)
                ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
                ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_NO_COUPON)
                ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_PERCENT_ACTION)->setDiscountAmount(50)->save();
        }
        if ($taxShipping) {
            $customerTaxClass = (int) Mage::getModel('customer/group')->getTaxClassId($groupId);
            $name = 'Shipping tax ' . uniqid();
            $taxClass = Mage::getModel('tax/class')->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
                ->setClassName($name)->save();
            $taxRate = Mage::getService('tax/rate')->save(Mage::getModel('tax/calculation_rate')
                ->setCode($name)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(20.0));
            Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')
                ->setCode($name)->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerTaxClass])
                ->setTaxProductClass([(int) $taxClass->getId()])->setTaxRate([(int) $taxRate->getId()]));
            $store->setConfig('tax/classes/shipping_tax_class', (string) $taxClass->getId());
            $store->setConfig('tax/calculation/apply_after_discount', '0');
        }

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, (int) $store->getId())->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@example.test')->setCustomerFirstname('Sample')->setCustomerLastname('Customer')
            ->setCustomerGroupId($groupId);
        $address = [
            'firstname' => 'Sample', 'lastname' => 'Customer', 'email' => 'customer@example.test',
            'street' => '1 Sample Street', 'city' => 'Los Angeles', 'region_id' => 12,
            'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100000',
        ];
        $quote->getBillingAddress()->addData($address);
        $quote->getShippingAddress()->addData($address)->setCollectShippingRates(true)->setShippingMethod('flatrate_flatrate');
        for ($index = 0; $index < 2; $index++) {
            $product = Mage::getModel('catalog/product')->setStoreId(0);
            $product->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
                ->setSku('shipping-only-discount-' . uniqid())->setName('Shipping discount product')->setWeight(1.0)->setPrice(10.0)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setTaxClassId(0)->setWebsiteIds([(int) $store->getWebsiteId()])
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20])
                ->save();
            $loaded = Mage::getModel('catalog/product')->setStoreId($store->getId())->load($product->getId());
            $cart->addProduct($quote, $loaded, new Maho\DataObject(['qty' => 1]), save: false);
        }
        $quote->setCouponCode($coupon);
        $quote->getPayment()->importData(['method' => 'checkmo']);
        $cart->collectAndSave($quote);
        $placed = Mage::getModel('sales/service_quote', $quote)->submitOrder();
        $order = Mage::getModel('sales/order')->load((int) $placed->getId());
        $storedItems = $order->getAllVisibleItems();
        $amounts = static fn(Maho\DataObject $row, string $field): array => [
            (float) $row->getData($field),
            (float) $row->getData('base_' . $field),
        ];
        $shippingDiscount = $shipping > 0.0 ? 0.01 : 0.0;
        $orderShippingDiscount = [(float) $order->getShippingDiscountAmount(), $shippingDiscount];
        $shippingTax = $taxShipping ? [0.0, 0.2] : [0.0, 0.0];
        $itemDiscount = $discountItems ? [$store->roundPrice(5.0 * $rate), 5.0] : [0.0, 0.0];
        $firstDocumentDiscount = [-($itemDiscount[0] + $orderShippingDiscount[0]), -($itemDiscount[1] + $shippingDiscount)];
        $secondDocumentDiscount = [-$itemDiscount[0], -$itemDiscount[1]];
        expect($amounts($order, 'shipping_discount_amount'))->toBe($orderShippingDiscount)
            ->and($amounts($order, 'shipping_amount')[1])->toBe($shipping)
            ->and($amounts($order, 'shipping_tax_amount'))->toBe($shippingTax)
            ->and(array_map(static fn($item): array => $amounts($item, 'discount_amount'), $storedItems))
            ->toBe([$itemDiscount, $itemDiscount]);
        if ($shippingDiscount > 0.0) {
            expect($order->getCouponCode())->toBe($coupon);
        }

        $documents = Mage::getService('sales/order_document');
        if ($cancelFirst) {
            $order->getPayment()->setIsTransactionPending(true);
            $pending = $documents->prepareInvoice($order, [(int) $storedItems[0]->getId() => 1]);
            $pending->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE)->register();
            Mage::getModel('core/resource_transaction')->addObject($pending)->addObject($order)->save();
            Mage::getModel('sales/order_invoice_api')->cancel($pending->getIncrementId());
            $canceled = Mage::getModel('sales/order_invoice')->load((int) $pending->getId());
            expect($canceled->isCanceled())->toBeTrue()
                ->and($amounts($canceled, 'shipping_amount')[1])->toBe($shipping)
                ->and($amounts($canceled, 'discount_amount'))->toBe($firstDocumentDiscount);
            $order = Mage::getModel('sales/order')->load((int) $placed->getId());
            expect($amounts($order, 'shipping_invoiced'))->toBe([0.0, 0.0]);
        }
        $invoices = [];
        foreach ($storedItems as $item) {
            $invoice = $documents->invoice(
                $order,
                [(int) $item->getId() => 1],
                captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
                sendEmail: false,
            );
            $invoice = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
            $invoices[] = $invoice;
            $order = Mage::getModel('sales/order')->load((int) $placed->getId());
        }
        $baseItemTotal = 10.0 - $itemDiscount[1];
        expect($amounts($invoices[0], 'shipping_amount'))->toBe($amounts($order, 'shipping_amount'))
            ->and($amounts($invoices[0], 'discount_amount'))->toBe($firstDocumentDiscount)
            ->and($amounts($invoices[0], 'shipping_tax_amount'))->toBe($shippingTax)
            ->and($amounts($invoices[0], 'grand_total')[1])->toBe(round($baseItemTotal + $shipping - $shippingDiscount + $shippingTax[1], 2))
            ->and($amounts($invoices[1], 'shipping_amount'))->toBe([0.0, 0.0])
            ->and($amounts($invoices[1], 'discount_amount'))->toBe($secondDocumentDiscount)
            ->and($amounts($invoices[1], 'shipping_tax_amount'))->toBe([0.0, 0.0])
            ->and($amounts($invoices[1], 'grand_total')[1])->toBe($baseItemTotal)
            ->and($amounts($order, 'shipping_invoiced'))->toBe($amounts($order, 'shipping_amount'))
            ->and($amounts($order, 'discount_invoiced')[1])->toBe($amounts($order, 'discount_amount')[1])
            ->and($amounts($order, 'total_invoiced'))->toBe($amounts($order, 'grand_total'));

        foreach ($invoices as $index => $invoice) {
            $memo = $documents->refund(
                $order,
                ['qtys' => [(int) $storedItems[$index]->getId() => 1]],
                offlineRefund: true,
                sendEmail: false,
                invoice: $invoice,
            );
            $memo = Mage::getModel('sales/order_creditmemo')->load((int) $memo->getId());
            expect($amounts($memo, 'shipping_amount'))->toBe($amounts($invoice, 'shipping_amount'))
                ->and($amounts($memo, 'discount_amount'))->toBe($amounts($invoice, 'discount_amount'))
                ->and($amounts($memo, 'shipping_tax_amount'))->toBe($amounts($invoice, 'shipping_tax_amount'))
                ->and($amounts($memo, 'grand_total'))->toBe($amounts($invoice, 'grand_total'));
            $order = Mage::getModel('sales/order')->load((int) $placed->getId());
        }
        expect($amounts($order, 'shipping_refunded'))->toBe($amounts($order, 'shipping_amount'))
            ->and($amounts($order, 'total_refunded'))->toBe($amounts($order, 'grand_total'));
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'shipping discount stored only in the base currency' => [0.004, 5.0],
    'shipping and its discount stored only in the base currency' => [0.004, 1.0, false, true],
    'replacement for a canceled invoice with base currency shipping' => [0.004, 1.0, true, true],
    'shipping tax stored only in the base currency' => [0.004, 1.0, false, false, true],
    'replacement for a canceled invoice' => [0.004, 5.0, true],
    'ordinary shipping discount in both currencies' => [1.0, 5.0],
    'free carrier rate' => [1.0, 0.0],
    'coupon covers the entire shipping charge' => [1.0, 0.01],
]);

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('invoices shipping and its discount once in each currency', function (
    float $rate,
    float $shipping,
    bool $cancelFirst = false,
    bool $discountItems = false,
    bool $taxShipping = false,
    bool $legacyFirst = false,
    bool $cancelFirstAfterSecond = false,
    ?float $replacementItemPrice = null,
    bool $openShippingInvoice = false,
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
        $shippingCoupon = $replacementItemPrice === null ? 0.01 : 0.05;
        $coupon = 'SHIPPING-ONLY-' . uniqid();
        $rule = Mage::getModel('salesrule/rule')->setName('Fixed shipping discount')->setIsActive(true)
            ->setWebsiteIds([(int) $store->getWebsiteId()])->setCustomerGroupIds([$groupId])
            ->setCouponType(Mage_SalesRule_Model_Rule::COUPON_TYPE_SPECIFIC)->setCouponCode($coupon)
            ->setSimpleAction(Mage_SalesRule_Model_Rule::BY_FIXED_ACTION)->setDiscountAmount($shippingCoupon)->setApplyToShipping(true);
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
                ->setSku('shipping-only-discount-' . uniqid())->setName('Shipping discount product')->setWeight(1.0)->setPrice($index === 0 ? ($replacementItemPrice ?? 10.0) : 10.0)
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
        $shippingDiscount = $shipping > 0.0 ? $shippingCoupon : 0.0;
        $orderShippingDiscount = [(float) $order->getShippingDiscountAmount(), $shippingDiscount];
        $shippingTax = $taxShipping ? [0.0, 0.2] : [0.0, 0.0];
        $itemDiscount = $discountItems ? [$store->roundPrice(5.0 * $rate), 5.0] : [0.0, 0.0];
        $firstItemDiscount = $replacementItemPrice === null
            ? $itemDiscount
            : [$store->roundPrice($replacementItemPrice * $rate / 2), $replacementItemPrice / 2];
        $firstDocumentDiscount = [-($firstItemDiscount[0] + $orderShippingDiscount[0]), -($firstItemDiscount[1] + $shippingDiscount)];
        $secondDocumentDiscount = [-$itemDiscount[0], -$itemDiscount[1]];
        expect($amounts($order, 'shipping_discount_amount'))->toBe($orderShippingDiscount)
            ->and($amounts($order, 'shipping_amount')[1])->toBe($shipping)
            ->and($amounts($order, 'shipping_tax_amount'))->toBe($shippingTax)
            ->and(array_map(static fn($item): array => $amounts($item, 'discount_amount'), $storedItems))
            ->toBe([$firstItemDiscount, $itemDiscount]);
        if ($shippingDiscount > 0.0) {
            expect($order->getCouponCode())->toBe($coupon);
        }

        $documents = Mage::getService('sales/order_document');
        $shippingOnSecondInvoice = $legacyFirst && !$cancelFirst;
        $invoices = [];
        if ($cancelFirst || $legacyFirst) {
            $leaveFirstOpen = $cancelFirst || $cancelFirstAfterSecond;
            if ($leaveFirstOpen) {
                $order->getPayment()->setIsTransactionPending(true);
            }
            $first = $documents->prepareInvoice($order, [(int) $storedItems[0]->getId() => 1]);
            if ($legacyFirst) {
                // Older invoices omitted the base shipping charge and still applied its discount.
                expect($first->getShippingAmount())->toBe(0.0);
                $first->setBaseGrandTotal($first->getBaseGrandTotal() - $first->getBaseShippingAmount())
                    ->setBaseShippingAmount(0.0)->setBaseShippingInclTax(0.0);
            }
            $first->setRequestedCaptureCase(
                $leaveFirstOpen ? Mage_Sales_Model_Order_Invoice::NOT_CAPTURE : Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE,
            )->register();
            Mage::getModel('core/resource_transaction')->addObject($first)->addObject($order)->save();
            if ($cancelFirst) {
                Mage::getModel('sales/order_invoice_api')->cancel($first->getIncrementId());
            }
            $first = Mage::getModel('sales/order_invoice')->load((int) $first->getId());
            expect($first->isCanceled())->toBe($cancelFirst)
                ->and($amounts($first, 'shipping_amount')[1])->toBe($legacyFirst ? 0.0 : $shipping)
                ->and($amounts($first, 'discount_amount'))->toBe($firstDocumentDiscount);
            $order = Mage::getModel('sales/order')->load((int) $placed->getId());
            if ($cancelFirst) {
                expect($amounts($order, 'shipping_invoiced'))->toBe([0.0, 0.0]);
            } else {
                $invoices[] = $first;
            }
        }
        $itemsToInvoice = $cancelFirstAfterSecond ? array_reverse($storedItems, true) : $storedItems;
        foreach ($itemsToInvoice as $index => $item) {
            if ($shippingOnSecondInvoice && $index === 0) {
                if (!$cancelFirstAfterSecond) {
                    continue;
                }
                Mage::getModel('sales/order_invoice_api')->cancel($invoices[0]->getIncrementId());
                $canceled = Mage::getModel('sales/order_invoice')->load((int) $invoices[0]->getId());
                expect($canceled->isCanceled())->toBeTrue();
                $order = Mage::getModel('sales/order')->load((int) $placed->getId());
            }
            if ($replacementItemPrice !== null && $index === 0) {
                $qtys = [(int) $item->getId() => 1];
                $baseTotal = $store->roundPrice($replacementItemPrice / 2 - $shippingCoupon);
                $paid = $order->getBaseTotalPaid();
                $invoiced = $order->getBaseTotalInvoiced();
                if ($baseTotal < 0.0) {
                    expect(fn() => $documents->invoice($order, $qtys, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false))
                        ->toThrow(Mage_Core_Exception::class, 'The remaining shipping discount exceeds the amount of this invoice.');
                    $order = Mage::getModel('sales/order')->load((int) $placed->getId());
                    expect($order->getBaseTotalPaid())->toBe($paid)
                        ->and($order->getBaseTotalInvoiced())->toBe($invoiced)
                        ->and((float) $order->getItemById($item->getId())->getQtyInvoiced())->toBe(0.0)
                        ->and($order->getInvoiceCollection()->getSize())->toBe(2);
                    if ($openShippingInvoice) {
                        Mage::getModel('sales/order_invoice_api')->cancel($invoices[1]->getIncrementId());
                        $order = Mage::getModel('sales/order')->load((int) $placed->getId());
                        $qtys[(int) $storedItems[1]->getId()] = 1;
                        $invoice = $documents->invoice($order, $qtys, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
                        expect($invoice->getBaseGrandTotal())->toEqualWithDelta(5.99, 0.000001)
                            ->and($invoice->getBaseShippingAmount())->toBe(1.0)
                            ->and($invoice->getBaseDiscountAmount())->toEqualWithDelta(-5.09, 0.000001);
                    }
                } else {
                    $invoice = $documents->invoice($order, $qtys, captureCase: Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE, sendEmail: false);
                    $persisted = Mage::getModel('sales/order_invoice')->load((int) $invoice->getId());
                    expect($persisted->getBaseGrandTotal())->toBe($baseTotal);
                }
                return;
            }
            if ($openShippingInvoice) {
                $order->getPayment()->setIsTransactionPending(true);
                $shippingInvoice = $documents->prepareInvoice($order, [(int) $item->getId() => 1]);
                $shippingInvoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::NOT_CAPTURE)->register();
                Mage::getModel('core/resource_transaction')->addObject($shippingInvoice)->addObject($order)->save();
                $invoices[] = $shippingInvoice;
                $order = Mage::getModel('sales/order')->load((int) $placed->getId());
                continue;
            }
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
        if ($cancelFirstAfterSecond) {
            expect($amounts($invoices[2], 'shipping_amount'))->toBe([0.0, 0.0])
                ->and($amounts($invoices[2], 'discount_amount'))->toBe($firstDocumentDiscount)
                ->and($amounts($invoices[2], 'grand_total')[1])->toBe(round($baseItemTotal - $shippingDiscount, 2));
        }
        $shippingAmounts = $amounts($order, 'shipping_amount');
        $firstShipping = $shippingOnSecondInvoice ? [0.0, 0.0] : $shippingAmounts;
        $secondShipping = $shippingOnSecondInvoice ? $shippingAmounts : [0.0, 0.0];
        expect($amounts($invoices[0], 'shipping_amount'))->toBe($firstShipping)
            ->and($amounts($invoices[0], 'discount_amount'))->toBe($firstDocumentDiscount)
            ->and($amounts($invoices[0], 'shipping_tax_amount'))->toBe($shippingTax)
            ->and($amounts($invoices[0], 'grand_total')[1])->toBe(round($baseItemTotal + $firstShipping[1] - $shippingDiscount + $shippingTax[1], 2))
            ->and($amounts($invoices[1], 'shipping_amount'))->toBe($secondShipping)
            ->and($amounts($invoices[1], 'discount_amount'))->toBe($secondDocumentDiscount)
            ->and($amounts($invoices[1], 'shipping_tax_amount'))->toBe([0.0, 0.0])
            ->and($amounts($invoices[1], 'grand_total')[1])->toBe(round($baseItemTotal + $secondShipping[1], 2))
            ->and($amounts($order, 'shipping_invoiced'))->toBe($amounts($order, 'shipping_amount'))
            ->and($amounts($order, 'discount_invoiced')[1])->toBe($amounts($order, 'discount_amount')[1])
            ->and($amounts($order, 'total_invoiced'))->toBe($amounts($order, 'grand_total'));

        if ($shippingOnSecondInvoice) {
            return;
        }
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
    'base currency shipping after an older invoice that already discounted it' => [0.004, 1.0, false, true, false, true],
    'base currency shipping after a canceled older invoice' => [0.004, 1.0, true, true, false, true],
    'replacement for an older invoice canceled after base currency shipping was invoiced' => [0.004, 1.0, false, true, false, true, true],
    'replacement remaining shipping discount exceeds the line total' => [0.004, 1.0, false, true, false, true, true, 0.08],
    'negative replacement can be retried after canceling the unpaid shipping invoice' => [0.004, 1.0, false, true, false, true, true, 0.08, true],
    'replacement remaining shipping discount leaves exactly zero' => [0.004, 1.0, false, true, false, true, true, 0.10],
    'replacement remaining shipping discount leaves one cent' => [0.004, 1.0, false, true, false, true, true, 0.12],
    'shipping tax stored only in the base currency' => [0.004, 1.0, false, false, true],
    'replacement for a canceled invoice' => [0.004, 5.0, true],
    'ordinary shipping discount in both currencies' => [1.0, 5.0],
    'free carrier rate' => [1.0, 0.0],
    'coupon covers the entire shipping charge' => [1.0, 0.01],
]);

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps tax in the base currency when the order currency tax rounds to zero', function (): void {
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
        $fixture = 'Document tax ' . uniqid();
        $groupId = Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
        $customerClassId = (int) Mage::getModel('customer/group')->getTaxClassId($groupId);
        $productClass = Mage::getModel('tax/class')->setClassType(Mage_Tax_Model_Class::TAX_CLASS_TYPE_PRODUCT)
            ->setClassName($fixture)->save();
        $rate = Mage::getService('tax/rate')->save(Mage::getModel('tax/calculation_rate')
            ->setCode($fixture)->setTaxCountryId('US')->setTaxPostcode('*')->setRate(20.0));
        Mage::getService('tax/rule')->save(Mage::getModel('tax/calculation_rule')
            ->setCode($fixture)->setPriority(0)->setPosition(0)->setTaxCustomerClass([$customerClassId])
            ->setTaxProductClass([(int) $productClass->getId()])->setTaxRate([(int) $rate->getId()]));

        $cart = Mage::getService('checkout/cart');
        $quote = $cart->create(null, (int) $store->getId())->setCustomerIsGuest(true)
            ->setCustomerEmail('customer@example.test')->setCustomerFirstname('Sample')->setCustomerLastname('Customer')
            ->setCustomerGroupId($groupId);
        $quote->getBillingAddress()->addData([
            'firstname' => 'Sample', 'lastname' => 'Customer', 'email' => 'customer@example.test',
            'street' => '1 Sample Street', 'city' => 'Los Angeles', 'region_id' => 12,
            'postcode' => '90210', 'country_id' => 'US', 'telephone' => '5550100000',
        ]);

        foreach ([[0.10, 2, (int) $productClass->getId()], [1.00, 1, 0]] as [$price, $qty, $classId]) {
            $product = Mage::getModel('catalog/product')->setStoreId(0);
            $product->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
                ->setSku('document-tax-' . uniqid())->setName($fixture)->setPrice($price)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setTaxClassId($classId)->setWebsiteIds([(int) $store->getWebsiteId()])
                ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 20])
                ->save();
            $product = Mage::getModel('catalog/product')->setStoreId($store->getId())->load($product->getId());
            $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
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

        $taxes = static fn($row): array => [(float) $row->getTaxAmount(), (float) $row->getBaseTaxAmount()];
        $amounts = static fn($row): array => [(float) $row->getGrandTotal(), (float) $row->getBaseGrandTotal()];
        $snapshot = [
            'currency' => [$order->getOrderCurrencyCode(), $order->getBaseCurrencyCode(), $order->getBaseToOrderRate()],
            'order' => [$taxes($order), $taxes($orderItem), $amounts($order)],
            'invoice' => [$taxes($invoice), $taxes($invoiceItem), $amounts($invoice)],
            'invoiced' => [$order->getTaxInvoiced(), $order->getBaseTaxInvoiced(), $orderItem->getTaxInvoiced(), $orderItem->getBaseTaxInvoiced()],
            'refund' => [$taxes($memo), $taxes($memoItem), $amounts($memo)],
            'refunded' => [$updatedOrder->getTaxRefunded(), $updatedOrder->getBaseTaxRefunded(), $updatedItem->getTaxRefunded(), $updatedItem->getBaseTaxRefunded()],
        ];

        expect($snapshot)->toBe([
            'currency' => ['EUR', 'USD', 0.1],
            'order' => [[0.0, 0.04], [0.0, 0.04], [0.12, 1.24]],
            'invoice' => [[0.0, 0.04], [0.0, 0.04], [0.02, 0.24]],
            'invoiced' => [0.0, 0.04, 0.0, 0.04],
            'refund' => [[0.0, 0.02], [0.0, 0.02], [0.01, 0.12]],
            'refunded' => [0.0, 0.02, 0.0, 0.02],
        ]);
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
});

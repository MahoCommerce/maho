<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

use Mage_SalesRule_Model_Rule as Rule;

uses(Tests\MahoBackendTestCase::class);

it('applies a fixed coupon up to the billable value of fractional lines', function (
    array $lines,
    string $currency,
    float $rate,
    float $budget,
    array $subtotals,
    array $discounts,
    array $lineDiscounts,
): void {
    $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
    $connection->beginTransaction();
    try {
        Mage::getModel('directory/currency')->saveRates(['USD' => ['EUR' => $rate]]);
        $store = setStoreDisplayCurrency($currency, 'USD,EUR');
        $storeId = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $group = Mage::getModel('customer/group')
            ->setCode('Billable coupon ' . uniqid())
            ->setTaxClassId((int) Mage::getModel('customer/group')->getTaxClassId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID))
            ->save();
        $coupon = 'BILLABLE-' . strtoupper(uniqid());
        Mage::getModel('salesrule/rule')
            ->setName('Billable cart coupon')
            ->setIsActive(true)
            ->setWebsiteIds([$websiteId])
            ->setCustomerGroupIds([(int) $group->getId()])
            ->setCouponType(Rule::COUPON_TYPE_SPECIFIC)
            ->setCouponCode($coupon)
            ->setSimpleAction(Rule::CART_FIXED_ACTION)
            ->setDiscountAmount($budget)
            ->save();

        $products = [];
        foreach ($lines as [$price, $qty]) {
            $product = Mage::getModel('catalog/product')->setStoreId(0);
            $product->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_VIRTUAL)
                ->setSku('billable-coupon-' . uniqid())
                ->setName('Fractional item')
                ->setPrice($price)
                ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
                ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
                ->setTaxClassId(0)
                ->setWebsiteIds([$websiteId])
                ->setStockData([
                    'use_config_manage_stock' => 0,
                    'manage_stock' => 0,
                    'is_in_stock' => 1,
                    'is_qty_decimal' => $qty != floor($qty),
                    'use_config_min_sale_qty' => 0,
                    'min_sale_qty' => 0.01,
                ])
                ->save();
            $products[] = [Mage::getModel('catalog/product')->setStoreId($storeId)->load($product->getId()), $qty];
        }

        $cart = Mage::getService('checkout/cart');
        $observed = [];
        foreach ([false, true] as $useCoupon) {
            $quote = $cart->create(null, $storeId)->setCustomerGroupId((int) $group->getId());
            $quote->setCustomerEmail('billable-coupon@example.com');
            $quote->getBillingAddress()->addData([
                'firstname' => 'Test',
                'lastname' => 'Buyer',
                'street' => '123 Test St',
                'city' => 'Beverly Hills',
                'region_id' => 12,
                'postcode' => '90210',
                'country_id' => 'US',
                'telephone' => '5551234567',
                'email' => 'billable-coupon@example.com',
            ]);
            foreach ($products as [$product, $qty]) {
                $cart->addProduct($quote, $product, new Maho\DataObject(['qty' => $qty]), save: false);
            }
            $cart->collectAndSave($quote);
            if ($useCoupon) {
                $cart->applyCoupon($quote, $coupon, Mage_Checkout_Model_Actor::Staff);
            }
            $quote = $cart->getById((int) $quote->getId());
            $quote->getPayment()->importData(['method' => (float) $quote->getGrandTotal() === 0.0 ? 'free' : 'checkmo']);
            $order = new Mage_Sales_Model_Service_Quote($quote)->submitOrder();
            $invoice = $order->prepareInvoice();
            $invoice->register();
            Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($order)->save();
            $invoice = Mage::getModel('sales/order_invoice')->load($invoice->getId());

            $observed[] = [
                'quote' => [$quote->getBaseSubtotal(), $quote->getSubtotal(), $quote->getBaseGrandTotal(), $quote->getGrandTotal()],
                'order' => [$order->getBaseSubtotal(), $order->getSubtotal(), $order->getBaseGrandTotal(), $order->getGrandTotal()],
                'invoice' => [$invoice->getBaseSubtotal(), $invoice->getSubtotal(), $invoice->getBaseGrandTotal(), $invoice->getGrandTotal()],
                'invoice_discount' => [$invoice->getBaseDiscountAmount(), $invoice->getDiscountAmount()],
                'rows' => array_map(
                    static fn(Mage_Sales_Model_Order_Invoice_Item $item): array => [
                        (float) $item->getBaseRowTotal(),
                        (float) $item->getRowTotal(),
                        (float) $item->getBaseDiscountAmount(),
                        (float) $item->getDiscountAmount(),
                    ],
                    $invoice->getAllItems(),
                ),
            ];
        }

        foreach ($observed as $index => $totals) {
            $applied = $index === 0 ? [0.0, 0.0] : $discounts;
            $expected = [...$subtotals, $subtotals[0] - $applied[0], $subtotals[1] - $applied[1]];
            foreach (['quote', 'order', 'invoice'] as $document) {
                foreach ($expected as $field => $amount) {
                    expect((float) $totals[$document][$field])->toEqualWithDelta($amount, 0.000001);
                }
            }
            expect((float) $totals['invoice_discount'][0])->toEqualWithDelta(-$applied[0], 0.000001)
                ->and((float) $totals['invoice_discount'][1])->toEqualWithDelta(-$applied[1], 0.000001);
            expect($totals['rows'])->toHaveCount(count($lines));
            foreach ($totals['rows'] as $line => [$baseRow, $row, $baseDiscount, $discount]) {
                [$expectedBaseDiscount, $expectedDiscount] = $index === 0 ? [0.0, 0.0] : $lineDiscounts[$line];
                expect($baseDiscount)->toBeLessThanOrEqual($baseRow + 0.000001)
                    ->and($discount)->toBeLessThanOrEqual($row + 0.000001)
                    ->and($baseDiscount)->toEqualWithDelta($expectedBaseDiscount, 0.000001)
                    ->and($discount)->toEqualWithDelta($expectedDiscount, 0.000001);
            }
        }
    } finally {
        $connection->rollBack();
        resetCurrencyState();
    }
})->with([
    'one fraction rounds up to a cent' => [
        [[0.03, 0.3]], 'USD', 1.0, 0.01, [0.01, 0.01], [0.01, 0.01], [[0.01, 0.01]],
    ],
    'one fraction in a converted currency' => [
        [[0.03, 0.3]], 'EUR', 2.0, 0.01, [0.01, 0.02], [0.01, 0.02], [[0.01, 0.02]],
    ],
    'a fraction that bills at zero does not use the coupon' => [
        [[0.03, 0.1]], 'USD', 1.0, 0.01, [0.0, 0.0], [0.0, 0.0], [[0.0, 0.0]],
    ],
    'a zero subtotal line does not take credit from an ordinary line' => [
        [[0.03, 0.1], [0.02, 1]], 'USD', 1.0, 0.01, [0.02, 0.02], [0.01, 0.01], [[0.0, 0.0], [0.01, 0.01]],
    ],
    'a billed fraction and an ordinary line share a coupon' => [
        [[0.03, 0.3], [0.03, 0.1], [0.02, 1]], 'USD', 1.0, 0.02, [0.03, 0.03], [0.02, 0.02], [[0.01, 0.01], [0.0, 0.0], [0.01, 0.01]],
    ],
    'each converted line uses its own billable amount' => [
        [[0.03, 0.3], [0.03, 0.1], [0.02, 1]], 'EUR', 2.0, 0.02, [0.03, 0.07], [0.02, 0.04], [[0.01, 0.01], [0.0, 0.0], [0.01, 0.03]],
    ],
    'a unit price rounds before the line total' => [
        [[0.0349, 0.45]], 'USD', 1.0, 0.02, [0.01, 0.01], [0.01, 0.01], [[0.01, 0.01]],
    ],
]);

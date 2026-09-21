<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Wave 5 converted the long tail of models. Database-shaped input (decimal and
 * integer columns arrive as strings on MySQL and PostgreSQL) must come back
 * typed, an unset key must stay null, and the annotations that named the wrong
 * type must now name the right one.
 */

dataset('long tail round trips', [
    'creditmemo decimal' => ['sales/order_creditmemo', 'grand_total', '25.5000', 25.5],
    'creditmemo flag' => ['sales/order_creditmemo', 'email_sent', '0', false],
    'invoice decimal' => ['sales/order_invoice', 'base_grand_total', '10.0000', 10.0],
    'invoice int' => ['sales/order_invoice', 'order_id', '42', 42],
    'shipment decimal' => ['sales/order_shipment', 'total_weight', '1.5000', 1.5],
    'shipment track int' => ['sales/order_shipment_track', 'parent_id', '11', 11],
    'stock item decimal' => ['cataloginventory/stock_item', 'qty', '3.0000', 3.0],
    'stock item flag' => ['cataloginventory/stock_item', 'is_in_stock', '1', true],
    'admin user int' => ['admin/user', 'user_id', '42', 42],
    'admin user flag' => ['admin/user', 'is_active', '1', true],
    'store int' => ['core/store', 'group_id', '3', 3],
    'salesrule decimal' => ['salesrule/rule', 'discount_amount', '5.0000', 5.0],
    'salesrule flag' => ['salesrule/rule', 'is_advanced', '1', true],
    'tax rate decimal' => ['tax/calculation_rate', 'rate', '8.2500', 8.25],
    'subscriber int' => ['newsletter/subscriber', 'subscriber_id', '7', 7],
    'oauth flag' => ['oauth/token', 'authorized', '1', true],
    'cron string' => ['cron/schedule', 'job_code', 'catalog_reindex', 'catalog_reindex'],
    'cms page string' => ['cms/page', 'title', 'About us', 'About us'],
    'rating int' => ['rating/rating', 'review_id', '4', 4],
]);

it('returns typed values from database-shaped input', function (string $model, string $key, string $in, float|int|bool|string $out) {
    $object = Mage::getModel($model);
    $object->setData($key, $in);

    $getter = 'get' . str_replace('_', '', ucwords($key, '_'));

    expect($object->$getter())->toBe($out);
})->with('long tail round trips');

it('returns null for a key the model does not hold', function () {
    expect(Mage::getModel('sales/order_creditmemo')->getGrandTotal())->toBeNull()
        ->and(Mage::getModel('sales/order_invoice')->getOrderId())->toBeNull()
        ->and(Mage::getModel('admin/user')->getIsActive())->toBeNull()
        ->and(Mage::getModel('cms/page')->getTitle())->toBeNull()
        ->and(Mage::getModel('cataloginventory/stock_item')->getQty())->toBeNull();
});

it('keeps string columns as strings', function () {
    $rate = Mage::getModel('tax/calculation_rate')->setData('tax_postcode', '01234');
    $track = Mage::getModel('sales/order_shipment_track')->setData('number', '007');

    expect($rate->getTaxPostcode())->toBe('01234')
        ->and($track->getNumber())->toBe('007');
});

it('reads and clears a session key through the clear flag', function () {
    $session = Mage::getSingleton('checkout/session');
    $session->setRedirectUrl('/checkout/cart');

    expect($session->getRedirectUrl())->toBe('/checkout/cart')
        ->and($session->getRedirectUrl(true))->toBe('/checkout/cart')
        ->and($session->getRedirectUrl())->toBeNull();
});

it('copies an order into an invoice and a creditmemo with typed values', function () {
    $order = Mage::getModel('sales/order');
    $order->setData('base_currency_code', 'EUR');
    $order->setData('store_to_base_rate', '1.5000');

    $invoice = Mage::getModel('sales/order_invoice');
    Mage::helper('core')->copyFieldset('sales_convert_order', 'to_invoice', $order, $invoice);

    $creditmemo = Mage::getModel('sales/order_creditmemo');
    Mage::helper('core')->copyFieldset('sales_convert_order', 'to_cm', $order, $creditmemo);

    expect($invoice->getBaseCurrencyCode())->toBe('EUR')
        ->and($invoice->getStoreToBaseRate())->toBe(1.5)
        ->and($creditmemo->getBaseCurrencyCode())->toBe('EUR')
        ->and($creditmemo->getStoreToBaseRate())->toBe(1.5);
});

it('reads the aggregator name from the map without an index argument', function () {
    $combine = Mage::getModel('salesrule/rule_condition_combine');
    $combine->setAggregator('all');

    expect($combine->getAggregatorName())->toBe('ALL');
});

it('types the keys whose annotation named the wrong type', function () {
    // a downloadable link is shareable with three states, not two
    $link = Mage::getModel('downloadable/link')->setData('is_shareable', '2');
    // the review customer id was annotated array
    $review = Mage::getModel('review/review')->setData('customer_id', '7');
    // the stock item product type is a code, not a number
    $item = Mage::getModel('cataloginventory/stock_item')->setData('type_id', 'simple');

    expect($link->getIsShareable())->toBe(2)
        ->and($review->getCustomerId())->toBe(7)
        ->and($item->getTypeId())->toBe('simple');
});

it('keeps a serialized key readable as an array before the resource writes it', function () {
    $config = Mage::getModel('core/config_data')->setValue(['one' => 1]);
    $profile = Mage::getModel('sales/recurring_profile')->setOrderInfo(['store_id' => 1]);

    expect($config->getValue())->toBe(['one' => 1])
        ->and($profile->getOrderInfo())->toBe(['store_id' => 1]);
});

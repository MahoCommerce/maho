<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rss
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function rss_key_create_order(?int $customerId = null): Mage_Sales_Model_Order
{
    $order = Mage::getModel('sales/order');
    $order->setIncrementId((string) random_int(900000000, 999999999));
    $order->setStoreId(1);
    $order->setData('state', Mage_Sales_Model_Order::STATE_COMPLETE);
    $order->setStatus('complete');
    $order->setCustomerEmail('rss-' . uniqid() . '@example.com');
    $order->setCustomerIsGuest($customerId === null ? 1 : 0);
    if ($customerId !== null) {
        $order->setCustomerId($customerId);
    }
    $order->setGrandTotal(10.00);
    $order->setBaseGrandTotal(10.00);
    $order->save();
    return $order;
}

function rss_key_forge(Mage_Sales_Model_Order $order, ?string $signature = null): string
{
    $data = [
        'order_id' => $order->getId(),
        'increment_id' => $order->getIncrementId(),
        'customer_id' => $order->getCustomerId(),
    ];
    if ($signature !== null) {
        $data['signature'] = $signature;
    }
    return base64_encode(json_encode($data));
}

beforeEach(function () {
    $this->helper = Mage::helper('rss/order');
});

afterEach(function () {
    foreach ($this->orders ?? [] as $order) {
        $order->delete();
    }
});

describe('Order status feed key', function () {
    it('accepts the key the helper generated', function () {
        $order = rss_key_create_order();
        $this->orders = [$order];

        $key = $this->helper->getStatusUrlKey($order);
        $found = $this->helper->getOrderByStatusUrlKey($key);

        expect($key)->not->toBe('');
        expect($found)->not->toBeNull();
        expect((int) $found->getId())->toBe((int) $order->getId());
    });

    it('rejects a key built from the public order ids alone', function () {
        $order = rss_key_create_order();
        $this->orders = [$order];

        expect($this->helper->getOrderByStatusUrlKey(rss_key_forge($order)))->toBeNull();
    });

    it('rejects a key with a wrong signature', function () {
        $order = rss_key_create_order();
        $this->orders = [$order];

        $forged = rss_key_forge($order, str_repeat('0', 64));

        expect($this->helper->getOrderByStatusUrlKey($forged))->toBeNull();
    });

    it('rejects a key signed for a different order', function () {
        $first = rss_key_create_order();
        $second = rss_key_create_order();
        $this->orders = [$first, $second];

        $data = json_decode(base64_decode($this->helper->getStatusUrlKey($first)), true);
        $data['order_id'] = $second->getId();
        $data['increment_id'] = $second->getIncrementId();
        $moved = base64_encode(json_encode($data));

        expect($this->helper->getOrderByStatusUrlKey($moved))->toBeNull();
    });

    it('fails closed when the order has no protect code', function () {
        $order = rss_key_create_order();
        $this->orders = [$order];
        $order->setData('protect_code', '')->save();

        $signature = hash_hmac('sha256', $order->getId() . ':' . $order->getIncrementId() . ':', '');

        expect($this->helper->getStatusUrlKey($order))->toBe('');
        expect($this->helper->getOrderByStatusUrlKey(rss_key_forge($order, $signature)))->toBeNull();
    });

    it('rejects malformed input', function () {
        expect($this->helper->getOrderByStatusUrlKey(''))->toBeNull();
        expect($this->helper->getOrderByStatusUrlKey('not-base64!'))->toBeNull();
        expect($this->helper->getOrderByStatusUrlKey(base64_encode('"string"')))->toBeNull();
    });
});

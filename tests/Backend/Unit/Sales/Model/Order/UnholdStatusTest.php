<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('restores the status an order had before it was held', function (): void {
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
        ->setStatus('processing');

    $order->hold()->unhold();

    expect($order->getState())->toBe(Mage_Sales_Model_Order::STATE_PROCESSING);
    expect($order->getStatus())->toBe('processing');
});

it('falls back to the default status of the previous state when the order had none', function (): void {
    $order = Mage::getModel('sales/order')->setStoreId(1)
        ->setState(Mage_Sales_Model_Order::STATE_NEW);

    $order->hold()->unhold();

    expect($order->getState())->toBe(Mage_Sales_Model_Order::STATE_NEW);
    expect($order->getStatus())->toBe('pending');
});

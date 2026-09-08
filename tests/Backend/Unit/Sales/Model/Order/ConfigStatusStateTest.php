<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Sales_Model_Order_Config::isStatusAssignedToState', function (): void {
    it('accepts a status assigned to the state', function (): void {
        $config = Mage::getSingleton('sales/order_config');

        expect($config->isStatusAssignedToState('processing', Mage_Sales_Model_Order::STATE_PROCESSING))->toBeTrue()
            ->and($config->isStatusAssignedToState('fraud', Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW))->toBeTrue();
    });

    it('rejects a status assigned to another state', function (): void {
        $config = Mage::getSingleton('sales/order_config');

        expect($config->isStatusAssignedToState('processing', Mage_Sales_Model_Order::STATE_CLOSED))->toBeFalse()
            ->and($config->isStatusAssignedToState('pending', Mage_Sales_Model_Order::STATE_PROCESSING))->toBeFalse();
    });

    it('rejects an unknown status or an unknown state', function (): void {
        $config = Mage::getSingleton('sales/order_config');

        expect($config->isStatusAssignedToState('no_such_status', Mage_Sales_Model_Order::STATE_NEW))->toBeFalse()
            ->and($config->isStatusAssignedToState('pending', 'no_such_state'))->toBeFalse();
    });
});

describe('Mage_Sales_Model_Order::isStatusValidForState', function (): void {
    it('checks against the order state by default', function (): void {
        $order = Mage::getModel('sales/order')->setData('state', Mage_Sales_Model_Order::STATE_CLOSED);

        expect($order->isStatusValidForState('closed'))->toBeTrue()
            ->and($order->isStatusValidForState('processing'))->toBeFalse();
    });

    it('checks against an explicit state when one is given', function (): void {
        $order = Mage::getModel('sales/order')->setData('state', Mage_Sales_Model_Order::STATE_CLOSED);

        expect($order->isStatusValidForState('processing', Mage_Sales_Model_Order::STATE_PROCESSING))->toBeTrue();
    });
});

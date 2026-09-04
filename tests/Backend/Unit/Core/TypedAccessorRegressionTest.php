<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Behaviour the magic __call accessors used to provide, which the typed
 * accessors must keep: the session read-and-clear flag, and total models
 * accepting the config element the totals collector actually hands them.
 */

it('clears session data when the getter is called with the clear flag', function () {
    /** @var Mage_Review_Model_Session $session */
    $session = Mage::getSingleton('review/session');
    $session->setFormData(['nickname' => 'tester']);

    expect($session->getFormData())->toBe(['nickname' => 'tester'])
        ->and($session->getFormData(true))->toBe(['nickname' => 'tester'])
        ->and($session->getFormData())->toBeNull();
});

it('accepts a config element as the total config node', function () {
    $node = Mage::getConfig()->getNode('global/sales/order_invoice/totals/subtotal');

    /** @var Mage_Sales_Model_Order_Total_Abstract $model */
    $model = Mage::getModel('sales/order_invoice_total_subtotal');
    $model->setTotalConfigNode($node);

    expect($model->getData('total_config_node'))->toBe($node);
});

it('collects invoice totals without a type error', function () {
    expect(Mage::getSingleton('sales/order_invoice_config')->getTotalModels())->not->toBeEmpty();
});

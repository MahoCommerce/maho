<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('enables carrier TLS verification by default', function () {
    $config = simplexml_load_file(Mage::getBaseDir('code') . '/core/Mage/Usa/etc/config.xml');

    expect((string) $config->default->carriers->ups->verify_peer)->toBe('1')
        ->and((string) $config->default->carriers->dhlint->verify_peer)->toBe('1');
});

it('reads the UPS verification flag from the UPS configuration', function () {
    Mage::app()->getStore()->setConfig('carriers/ups/verify_peer', '1');

    $auth = Mage::getModel('usa/shipping_carrier_upsAuth');

    expect($auth->getConfigFlag('verify_peer'))->toBeTrue();
});

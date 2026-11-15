<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('returns correct payment action source options', function () {
    $source = Mage::getModel('paypal/system_config_source_paymentAction');
    $options = $source->toOptionArray();

    expect($options)->toBeArray();
    expect($options)->toHaveCount(2);

    $values = array_column($options, 'value');
    expect($values)->toContain('authorize');
    expect($values)->toContain('capture');
});

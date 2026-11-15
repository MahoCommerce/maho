<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('resolves each webhook handler of the processor map to a handler', function () {
    $handlers = (new ReflectionClass(Maho_Paypal_Model_Webhook_Processor::class))->getConstant('HANDLER_MAP');

    expect($handlers)->not->toBeEmpty();
    foreach ($handlers as $eventType => $alias) {
        expect(Mage::getModel($alias))->toBeInstanceOf(Maho_Paypal_Model_Webhook_Handler_AbstractHandler::class, $eventType);
    }
});

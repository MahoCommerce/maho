<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class)->group('frontend', 'paypal');

it('can instantiate webhook processor', function () {
    $processor = Mage::getModel('paypal/webhook_processor');
    expect($processor)->toBeInstanceOf(Maho_Paypal_Model_Webhook_Processor::class);
});

it('can instantiate webhook verifier', function () {
    $verifier = Mage::getModel('paypal/webhook_verifier');
    expect($verifier)->toBeInstanceOf(Maho_Paypal_Model_Webhook_Verifier::class);
});

it('can instantiate webhook event model', function () {
    $event = Mage::getModel('paypal/webhook_event');
    expect($event)->toBeInstanceOf(Maho_Paypal_Model_Webhook_Event::class);
});

it('can instantiate all webhook handlers', function () {
    $handlers = [
        'paypal/webhook_handler_captureCompleted',
        'paypal/webhook_handler_capturePending',
        'paypal/webhook_handler_captureDeclined',
        'paypal/webhook_handler_captureRefunded',
        'paypal/webhook_handler_captureReversed',
        'paypal/webhook_handler_authorizationCreated',
        'paypal/webhook_handler_authorizationVoided',
        'paypal/webhook_handler_disputeCreated',
        'paypal/webhook_handler_disputeUpdated',
        'paypal/webhook_handler_disputeResolved',
        'paypal/webhook_handler_vaultTokenCreated',
        'paypal/webhook_handler_vaultTokenDeleted',
    ];

    foreach ($handlers as $handler) {
        $instance = Mage::getModel($handler);
        expect($instance)->toBeInstanceOf(Maho_Paypal_Model_Webhook_Handler_AbstractHandler::class);
    }
});

it('can instantiate webhook event collection', function () {
    $collection = Mage::getResourceModel('paypal/webhook_event_collection');
    expect($collection)->toBeInstanceOf(Maho_Paypal_Model_Resource_Webhook_Event_Collection::class);
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('compiles MessageHandler attributes into maho_attributes.php', function () {
    $compiled = Maho::getCompiledAttributes();

    expect($compiled)->toHaveKey('messageHandlers');
    expect($compiled['messageHandlers'])->toHaveKey(Mage_Core_Model_Email_SendMessage::class);

    $handlers = $compiled['messageHandlers'][Mage_Core_Model_Email_SendMessage::class];
    expect($handlers)->toHaveCount(1);
    expect($handlers[0]['class'])->toBe(Mage_Core_Model_Email_SendMessageHandler::class);
    expect($handlers[0]['method'])->toBe('__invoke');
    expect($handlers[0]['module'])->toBe('Mage_Core');
    expect($handlers[0]['alias'])->toBe('core/email_sendMessageHandler');
});

it('exposes compiled handlers through the runtime registry', function () {
    expect(\Maho\Queue\HandlerRegistry::allowedMessageClasses())
        ->toContain(Mage_Core_Model_Email_SendMessage::class);
});

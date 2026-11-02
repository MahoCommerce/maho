<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * GHSA-23v7-qrrp-4m96: the XML-RPC and JSON-RPC servers publish every public method of the
 * handler, so a client could call the PHP error handler with no session.
 */
it('does not publish the PHP error handler as an API method', function (): void {
    $method = new ReflectionMethod(Mage_Api_Model_Server_Handler_Abstract::class, 'handlePhpError');

    expect($method->isPublic())->toBeFalse();
});

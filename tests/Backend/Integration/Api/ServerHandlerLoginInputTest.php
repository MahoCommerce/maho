<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

class ApiLoginInputTestHandler extends Mage_Api_Model_Server_Handler_Abstract
{
    public ?string $faultName = null;

    #[\Override]
    protected function _fault($faultName, $resourceName = null, $customMessage = null): void
    {
        $this->faultName = $faultName;
    }
}

/**
 * Mage_Api_Model_User::loadByUsername() takes a string, so an array credential used to escape
 * the handler as a TypeError, which its catch (Exception) block never sees.
 */
it('rejects non-string api credentials as an invalid request parameter', function (#[\SensitiveParameter] mixed $username, #[\SensitiveParameter] mixed $apiKey) {
    $handler = new ApiLoginInputTestHandler();

    expect($handler->login($username, $apiKey))->toBeNull()
        ->and($handler->faultName)->toBe('invalid_request_param');
})->with([
    'array username' => [['apiuser'], 'apikey'],
    'array api key' => ['apiuser', ['apikey']],
    'object username' => [new stdClass(), 'apikey'],
    'empty username' => ['', 'apikey'],
    'missing api key' => ['apiuser', null],
]);

it('accepts the username 0 as a credential and attempts the login', function () {
    $handler = new ApiLoginInputTestHandler();

    $handler->login('0', 'wrong-key');

    expect($handler->faultName)->toBe('access_denied');
});

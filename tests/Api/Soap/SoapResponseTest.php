<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

use Tests\Helpers\ApiV2Helper;

it('sends a complete SOAP v2 response with one content length', function (): void {
    $client = new SoapClient(ApiV2Helper::getBaseUrlPublic() . '/api/v2_soap?wsdl=1', [
        'trace' => true,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'connection_timeout' => 5,
    ]);

    $sessionId = $client->__soapCall('startSession', []);
    expect($sessionId)->toBeString()->not->toBeEmpty();

    try {
        $body = (string) $client->__getLastResponse();
        $headers = (string) $client->__getLastResponseHeaders();
        preg_match_all('/^Content-Length:\h*(\d+)\h*\r?$/mi', $headers, $matches);
        expect($matches[1])->toBe([(string) strlen($body)]);
    } finally {
        $client->__soapCall('endSession', [$sessionId]);
    }
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

uses(Tests\MahoBackendTestCase::class);

function uspsRestClient(HttpClientInterface $http): Mage_Usa_Model_Shipping_Carrier_Usps_RestClient
{
    $oauth = new class extends Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient {
        public function __construct() {}

        #[\Override]
        public function getAccessToken(): string
        {
            return 'synthetic-token';
        }
    };

    return new class ($oauth, $http) extends Mage_Usa_Model_Shipping_Carrier_Usps_RestClient {
        public function __construct(Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient $oauth, private HttpClientInterface $http)
        {
            parent::__construct($oauth);
        }

        #[\Override]
        protected function _createHttpClient(): HttpClientInterface
        {
            return $this->http;
        }
    };
}

it('keeps USPS HTTP error details and the original exception', function (
    int $status,
    string $body,
    string $type,
    ?array $response,
    ?string $error,
    ?string $previous,
): void {
    $http = new MockHttpClient(new MockResponse($body, [
        'http_code' => $status,
        'response_headers' => ['content-type: ' . $type],
    ]));
    $actualResponse = null;
    $actualError = null;
    $actualPrevious = null;
    try {
        $actualResponse = uspsRestClient($http)->getTracking('SYNTHETIC');
    } catch (Exception $exception) {
        $actualError = $exception->getMessage();
        $actualPrevious = $exception->getPrevious()?->getMessage();
    }

    expect($actualResponse)->toBe($response)
        ->and($actualError)->toBe($error)
        ->and($actualPrevious)->toBe($previous)
        ->and($http->getRequestsCount())->toBe(1);
})->with([
    'json success' => [200, '{"trackingEvents":[]}', 'application/json', ['trackingEvents' => []], null, null],
    'binary success' => [201, 'LABEL', 'application/pdf', ['content' => base64_encode('LABEL'), 'contentType' => 'application/pdf'], null, null],
    'error in HTTP 200' => [200, '{"error":{"message":"Address invalid"}}', 'application/json', null, 'USPS API Error: Address invalid', null],
    'simple error in HTTP 400' => [400, '{"error":{"message":"Address invalid"}}', 'application/json', null, 'USPS API Error: Address invalid', 'USPS API returned unexpected status code: 400'],
    'nested error in HTTP 422' => [422, '{"error":{"errors":[{"message":"Weight invalid"},{"detail":"Size invalid"}]}}', 'application/json', null, 'USPS API Error: Weight invalid; Size invalid', 'USPS API returned unexpected status code: 422'],
    'not JSON in HTTP 500' => [500, '<html>Unavailable</html>', 'text/html', null, 'USPS API returned unexpected status code: 500', null],
]);

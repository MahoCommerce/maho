<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

uses(Tests\MahoBackendTestCase::class);

it('keeps UPS tokens separate for each credential set and endpoint', function (): void {
    $auth = new class extends Mage_Usa_Model_Shipping_Carrier_UpsAuth {
        public int $requests = 0;

        protected function fetchAccessToken(string $clientId, string $clientSecret, string $clientUrl): array
        {
            $this->requests++;
            return [
                'access_token' => "{$clientId}|{$clientSecret}|{$clientUrl}",
                'expires_in' => 60,
            ];
        }
    };
    $clientId = uniqid('ups-', true);

    $first = $auth->getAccessToken($clientId, 'secret-a', 'https://ups.test/a');
    $same = $auth->getAccessToken($clientId, 'secret-a', 'https://ups.test/a');
    $otherSecret = $auth->getAccessToken($clientId, 'secret-b', 'https://ups.test/a');
    $otherEndpoint = $auth->getAccessToken($clientId, 'secret-a', 'https://ups.test/b');

    expect($same)->toBe($first)
        ->and($otherSecret)->not->toBe($first)
        ->and($otherEndpoint)->not->toBe($first)
        ->and($auth->requests)->toBe(3);
});

it('keeps USPS tokens separate when only the client secret changes', function (): void {
    $clientId = uniqid('usps-', true);
    $first = new class ($clientId, 'secret-a', 'https://usps.test', 'token-a') extends Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient {
        public function __construct(string $clientId, string $clientSecret, string $baseUrl, private string $token)
        {
            parent::__construct($clientId, $clientSecret, $baseUrl);
        }

        protected function fetchNewToken(): array
        {
            return ['access_token' => $this->token, 'expires_in' => 60];
        }
    };
    $second = new class ($clientId, 'secret-b', 'https://usps.test', 'token-b') extends Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient {
        public function __construct(string $clientId, string $clientSecret, string $baseUrl, private string $token)
        {
            parent::__construct($clientId, $clientSecret, $baseUrl);
        }

        protected function fetchNewToken(): array
        {
            return ['access_token' => $this->token, 'expires_in' => 60];
        }
    };

    expect($first->getAccessToken())->toBe('token-a')
        ->and($second->getAccessToken())->toBe('token-b');
});

it('clears a rejected USPS token and retries the request once', function (): void {
    $oauth = new class ('id', 'secret', 'https://usps.test') extends Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient {
        public int $tokens = 0;
        public int $clears = 0;

        public function __construct(string $clientId, string $clientSecret, string $baseUrl)
        {
            parent::__construct($clientId, $clientSecret, $baseUrl);
        }

        public function getAccessToken(): string
        {
            return ++$this->tokens === 1 ? 'stale-token' : 'fresh-token';
        }

        public function clearAccessToken(): void
        {
            $this->clears++;
        }
    };
    $http = new MockHttpClient([
        new MockResponse('{"error":{"message":"Unauthorized"}}', [
            'http_code' => 401,
            'response_headers' => ['content-type: application/json'],
        ]),
        new MockResponse('{"rates":[]}', [
            'http_code' => 200,
            'response_headers' => ['content-type: application/json'],
        ]),
    ]);
    $client = new class ($oauth, $http) extends Mage_Usa_Model_Shipping_Carrier_Usps_RestClient {
        public function __construct(Mage_Usa_Model_Shipping_Carrier_Usps_OAuthClient $oauth, private HttpClientInterface $http)
        {
            parent::__construct($oauth);
        }

        protected function _createHttpClient(): HttpClientInterface
        {
            return $this->http;
        }
    };

    expect($client->getShippingOptions([]))->toBe(['rates' => []])
        ->and($oauth->tokens)->toBe(2)
        ->and($oauth->clears)->toBe(1)
        ->and($http->getRequestsCount())->toBe(2);
});

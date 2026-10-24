<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

uses(Tests\MahoBackendTestCase::class)->group('backend', 'paypal');

function paypalTokenClient(HttpClientInterface $http, string $clientId): Maho_Paypal_Model_Api_Client
{
    $client = new class ($http) extends Maho_Paypal_Model_Api_Client {
        public function __construct(private HttpClientInterface $http)
        {
            parent::__construct();
        }

        #[\Override]
        protected function _createHttpClient(): HttpClientInterface
        {
            return $this->http;
        }
    };

    return $client->setExplicitCredentials($clientId, 'secret', true);
}

function paypalTokenResponse(#[\SensitiveParameter] string $token, int $expiresIn = 900): MockResponse
{
    return new MockResponse(json_encode(['access_token' => $token, 'expires_in' => $expiresIn]));
}

it('requests a new client token only when the cached one has less than 10 minutes left', function (int $expiresIn, int $expectedRequests) {
    $requests = 0;
    $http = new MockHttpClient(function () use (&$requests, $expiresIn) {
        $requests++;
        return paypalTokenResponse("token-{$requests}", $expiresIn);
    });
    $clientId = uniqid('client-', true);

    paypalTokenClient($http, $clientId)->generateClientToken();
    paypalTokenClient($http, $clientId)->generateClientToken();

    expect($requests)->toBe($expectedRequests);
})->with([
    'a token that expires in 15 minutes' => [900, 1],
    'a token that expires in 8 minutes' => [480, 2],
]);

it('retries the token request once when it does not reach PayPal', function () {
    $durations = [];
    $responses = [
        new MockResponse('', ['error' => 'Recv failure: Connection reset by peer']),
        paypalTokenResponse('token-after-retry'),
    ];
    $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$durations, &$responses) {
        $durations[] = $options['max_duration'];
        return array_shift($responses);
    });

    $token = paypalTokenClient($http, uniqid('client-', true))->generateClientToken();

    expect($token)->toBe('token-after-retry')
        ->and($durations)->toHaveCount(2)
        ->and(max($durations))->toBeLessThanOrEqual(10.0);
});

it('throws when the retried token request does not reach PayPal either', function () {
    $requests = 0;
    $http = new MockHttpClient(function () use (&$requests) {
        $requests++;
        return new MockResponse('', ['error' => 'Could not resolve host: api-m.sandbox.paypal.com']);
    });
    $client = paypalTokenClient($http, uniqid('client-', true));

    expect(fn() => $client->generateClientToken())->toThrow(TransportException::class)
        ->and($requests)->toBe(2);
});

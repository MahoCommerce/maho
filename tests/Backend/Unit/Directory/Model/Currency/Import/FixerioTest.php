<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\Helpers\FixerioHarness;

uses(Tests\MahoBackendTestCase::class);

/**
 * Records every request the importer makes and replies with the given bodies in order.
 * The last body is reused if the importer asks for more.
 */
function fixerioClient(array &$requests, string ...$bodies): MockHttpClient
{
    return new MockHttpClient(function (string $method, string $url) use (&$requests, $bodies): MockResponse {
        $requests[] = $url;
        return new MockResponse($bodies[min(count($requests) - 1, count($bodies) - 1)]);
    });
}

function fixerioRates(array $rates): string
{
    return json_encode(['success' => true, 'base' => 'EUR', 'date' => '2026-08-05', 'rates' => $rates]);
}

function fixerioError(int $code, string $type): string
{
    return json_encode(['success' => false, 'error' => ['code' => $code, 'type' => $type]]);
}

function fixerioQuery(string $url): array
{
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    return $query;
}

describe('Fixer.io currency import', function () {
    beforeEach(function () {
        FixerioHarness::storeApiKey('test-access-key');
        $this->requests = [];
    });

    it('asks the service for every allowed currency in a single request', function () {
        $importer = (new FixerioHarness())
            ->setCurrencies(['GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(fixerioClient($this->requests, fixerioRates(['GBP' => 0.85, 'USD' => 1.10])));

        $importer->fetchRates();

        expect($this->requests)->toHaveCount(1);

        $url = $this->requests[0];
        expect($url)->toStartWith('https://data.fixer.io/api/latest');

        $query = fixerioQuery($url);
        expect($query['access_key'])->toBe('test-access-key');
        expect(explode(',', $query['symbols']))->toEqualCanonicalizing(['EUR', 'GBP', 'USD']);
    });

    it('derives cross-rates for each base currency from the EUR-based response', function () {
        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['EUR', 'USD'])
            ->setHttpClient(fixerioClient($this->requests, fixerioRates(['GBP' => 0.85, 'USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe([]);
        expect(array_keys($rates))->toBe(['EUR', 'USD']);

        expect($rates['EUR']['EUR'])->toEqual(1);
        expect($rates['EUR']['GBP'])->toEqualWithDelta(0.85, 0.000001);
        expect($rates['EUR']['USD'])->toEqualWithDelta(1.10, 0.000001);

        expect($rates['USD']['USD'])->toEqual(1);
        expect($rates['USD']['EUR'])->toEqualWithDelta(1 / 1.10, 0.000001);
        expect($rates['USD']['GBP'])->toEqualWithDelta(0.85 / 1.10, 0.000001);
    });

    it('reports an invalid API key instead of writing rates', function () {
        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(fixerioClient($this->requests, fixerioError(101, 'invalid_access_key')));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())
            ->toBe(['No API Key was specified or an invalid API Key was specified.']);
        expect($rates)->toBe(['USD' => ['EUR' => null, 'USD' => null]]);
    });

    it('maps the other documented service error codes', function () {
        $cases = [
            [102, 'The account this API request is coming from is inactive.'],
            [104, 'The maximum allowed API amount of monthly API requests has been reached.'],
            [105, 'The "EUR" is not allowed as base currency for your subscription plan.'],
            [106, 'The current request did not return any results.'],
            [201, 'An invalid base currency has been entered.'],
            [202, 'One or more invalid symbols have been specified.'],
        ];

        foreach ($cases as [$code, $message]) {
            $importer = (new FixerioHarness())
                ->setCurrencies(['EUR', 'USD'], ['USD'])
                ->setHttpClient(fixerioClient($this->requests, fixerioError($code, 'error')));

            $importer->fetchRates();

            expect($importer->getMessages())->toBe([$message]);
        }
    });

    it('falls back to a generic message for an unrecognized response', function () {
        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(fixerioClient($this->requests, '<html>gateway error</html>'));

        $rates = $importer->fetchRates();

        expect($importer->getMessages())->toBe(["Currency rates can't be retrieved."]);
        expect($rates['USD'])->toBe(['EUR' => null, 'USD' => null]);
    });

    it('does not call the service when no API key is configured', function () {
        FixerioHarness::storeApiKey('');

        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(fixerioClient($this->requests, fixerioRates(['USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($this->requests)->toBe([]);
        expect($importer->getMessages())
            ->toBe(['No API Key was specified or an invalid API Key was specified.']);
        expect($rates['USD'])->toBe(['EUR' => null, 'USD' => null]);
    });

    it('keeps the rates it got and flags only the currencies the service omitted', function () {
        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'GBP', 'USD'], ['USD'])
            ->setHttpClient(fixerioClient($this->requests, fixerioRates(['USD' => 1.10])));

        $rates = $importer->fetchRates();

        expect($rates['USD']['EUR'])->toEqualWithDelta(1 / 1.10, 0.000001);
        expect($rates['USD']['GBP'])->toBeNull();
        expect($importer->getMessages())->toBe(['Unable to calculate rate for USD to GBP.']);
    });

    it('retries once when the request fails at transport level', function () {
        $attempts = 0;
        $client = new MockHttpClient(function () use (&$attempts): MockResponse {
            $attempts++;
            if ($attempts === 1) {
                throw new TransportException('Connection timed out');
            }
            return new MockResponse(fixerioRates(['USD' => 1.10]));
        });

        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient($client);

        $rates = $importer->fetchRates();

        expect($attempts)->toBe(2);
        expect($rates['USD']['EUR'])->toEqualWithDelta(1 / 1.10, 0.000001);
    });

    it('sends the stored key decrypted, exactly as it was entered in the admin', function () {
        $key = 'aB3-key_with.punctuation';
        FixerioHarness::storeApiKey($key);

        $importer = (new FixerioHarness())
            ->setCurrencies(['EUR', 'USD'], ['USD'])
            ->setHttpClient(fixerioClient($this->requests, fixerioRates(['USD' => 1.10])));

        $importer->fetchRates();

        expect(Mage::getStoreConfig(FixerioHarness::XML_PATH_FIXERIO_API_KEY))->toBe($key);
        expect(fixerioQuery($this->requests[0])['access_key'])->toBe($key);
    });
});

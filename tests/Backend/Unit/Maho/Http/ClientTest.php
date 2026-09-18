<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Http
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function httpClientDefaultOptions(\Symfony\Contracts\HttpClient\HttpClientInterface $client): array
{
    return new ReflectionProperty($client, 'defaultOptions')->getValue($client);
}

describe('Maho\Http\Client', function () {
    it('caps the connect phase by default', function () {
        $client = \Maho\Http\Client::create(['timeout' => 5]);

        expect(httpClientDefaultOptions($client)['max_connect_duration'])->toBe(10.0);
    });

    it('lets the caller override the connect cap', function () {
        $client = \Maho\Http\Client::create(['max_connect_duration' => 3]);

        expect(httpClientDefaultOptions($client)['max_connect_duration'])->toBe(3.0);
    });
});

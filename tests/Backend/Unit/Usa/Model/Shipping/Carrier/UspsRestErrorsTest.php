<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\Process\Process;

it('preserves USPS HTTP error details and their original exception', function (): void {
    // A separate process replaces only factory/bootstrap seams, without an App or HTTP request.
    $vendor = dirname((new ReflectionClass(MockHttpClient::class))->getFileName(), 3);
    $process = new Process([
        PHP_BINARY,
        '-n',
        __DIR__ . '/_fixtures/usps-rest-errors.php',
        dirname(__DIR__, 7),
        $vendor,
    ]);
    $process->setTimeout(10);
    $process->mustRun();
    $actual = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    foreach ([
        'json-success' => [['trackingEvents' => []], null, null],
        'binary-success' => [['content' => base64_encode('OWNED-LABEL'), 'contentType' => 'application/pdf'], null, null],
        'success-with-error' => [null, 'USPS API Error: Fixture address invalid', null],
        'http-simple-error' => [null, 'USPS API Error: Fixture address invalid', 'USPS API returned unexpected status code: 400'],
        'http-nested-error' => [null, 'USPS API Error: Fixture weight invalid; Fixture size invalid', 'USPS API returned unexpected status code: 422'],
        'http-malformed-error' => [null, 'USPS API returned unexpected status code: 500', null],
    ] as $name => [$response, $error, $previous]) {
        expect($actual[$name]['response'])->toBe($response)
            ->and($actual[$name]['error'])->toBe($error)
            ->and($actual[$name]['previous'])->toBe($previous)
            ->and($actual[$name]['requests'])->toBe(1);
    }
});

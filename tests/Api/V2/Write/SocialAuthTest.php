<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * HTTP-level validation surface of POST /customers/social-auth. Deep token
 * verification is covered in-process by the Backend and Frontend suites; the
 * providers are unconfigured here, so every request must fail cleanly.
 */
it('rejects a request without provider and providerToken', function (): void {
    $response = apiPost('/api/rest/v2/customers/social-auth', []);
    expect($response['status'])->toBe(400);
});

it('rejects an unsupported provider', function (): void {
    $response = apiPost('/api/rest/v2/customers/social-auth', [
        'provider' => 'myspace',
        'providerToken' => 'irrelevant',
    ]);
    expect($response['status'])->toBe(400);
});

it('rejects an unconfigured provider with a garbage token', function (): void {
    $response = apiPost('/api/rest/v2/customers/social-auth', [
        'provider' => 'google',
        'providerToken' => 'garbage-token',
    ]);
    expect($response['status'])->toBe(400);
});

it('does not echo the inbound credential back on failure', function (): void {
    $response = apiPost('/api/rest/v2/customers/social-auth', [
        'provider' => 'google',
        'providerToken' => 'garbage-token-echo-check',
    ]);
    expect(json_encode($response['json'] ?? []))->not->toContain('garbage-token-echo-check');
});

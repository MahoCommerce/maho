<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * API v2 Malformed Request Body Tests
 *
 * A broken request body is a client error: every body the Symfony Serializer
 * rejects while deserializing into an input DTO must surface as 400
 * bad_request, never 500 internal_server_error (issue #1116). Covers the
 * DeserializeProvider path, which bypasses Processor::parseRequestBody()'s
 * own JSON handling.
 *
 * @group write
 */

describe('Malformed JSON request bodies', function (): void {

    it('returns 400 for syntactically invalid JSON on a public write endpoint', function (): void {
        $response = apiPostRaw('/api/rest/v2/newsletter/subscribe', '{invalid');

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('bad_request');
        expect($response['json']['message'])->toBe('Invalid JSON in request body');
        expect($response['json']['code'])->toBe(400);
    });

    it('returns 400 for an empty request body', function (): void {
        $response = apiPostRaw('/api/rest/v2/newsletter/subscribe', '');

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('bad_request');
        expect($response['json']['message'])->toBe('Invalid JSON in request body');
    });

    it('returns 400 when a JSON value has the wrong type for the DTO', function (): void {
        $response = apiPostRaw('/api/rest/v2/newsletter/subscribe', '{"email": {"nested": true}}');

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('bad_request');
        expect($response['json']['message'])->not->toBe('An internal error occurred');
        expect($response['json']['message'])->not->toBe('');
    });

    it('returns 400 for a JSON root of the wrong type', function (): void {
        $response = apiPostRaw('/api/rest/v2/newsletter/subscribe', '"just a string"');

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('bad_request');
        expect($response['json']['message'])->not->toBe('An internal error occurred');
    });

    it('returns 400 for malformed JSON on an authenticated write endpoint', function (): void {
        $token = serviceToken(['cms-pages/write']);
        $response = apiPostRaw('/api/rest/v2/cms-pages', '{invalid', $token);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('bad_request');
        expect($response['json']['message'])->toBe('Invalid JSON in request body');
    });

    it('still returns the domain validation error for valid JSON with an invalid value', function (): void {
        $response = apiPost('/api/rest/v2/newsletter/subscribe', ['email' => 'not-an-email']);

        expect($response['status'])->toBe(400);
        expect($response['json']['error'])->toBe('bad_request');
        expect($response['json']['message'])->toBe('Invalid email address');
    });
});

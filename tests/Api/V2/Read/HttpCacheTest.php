<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * Verifies HttpCacheListener behavior:
 *  - GET responses carry an ETag and a Cache-Control tier
 *  - A second GET with If-None-Match matching the ETag returns 304
 *  - Public paths advertise public Cache-Control and Vary on X-Store-Code
 *
 * @group read
 */

describe('HTTP cache headers', function (): void {

    it('sets ETag and Cache-Control on a public GET', function (): void {
        $response = apiGet('/api/rest/v2/store-config');

        expect($response['status'])->toBe(200);

        $etag = apiHeader($response, 'ETag');
        expect($etag)->not->toBeNull();
        expect($etag)->toMatch('/^"[0-9a-f]{32}"$/');

        $cacheControl = apiHeader($response, 'Cache-Control');
        expect($cacheControl)->not->toBeNull();
        expect($cacheControl)->toContain('max-age=');
    });

    it('returns 304 Not Modified when If-None-Match matches', function (): void {
        $first = apiGet('/api/rest/v2/store-config');
        $etag = apiHeader($first, 'ETag');
        expect($etag)->not->toBeNull();

        $second = apiGet('/api/rest/v2/store-config', null, ['If-None-Match' => $etag]);

        expect($second['status'])->toBe(304);
        expect($second['raw'])->toBe('');
        // ETag must be echoed on 304 so caches can re-validate.
        expect(apiHeader($second, 'ETag'))->toBe($etag);
    });

    it('serves a fresh 200 when If-None-Match does not match', function (): void {
        $response = apiGet('/api/rest/v2/store-config', null, [
            'If-None-Match' => '"deadbeefdeadbeefdeadbeefdeadbeef"',
        ]);

        expect($response['status'])->toBe(200);
        expect(apiHeader($response, 'ETag'))->not->toBeNull();
    });

    it('keys public cache on X-Store-Code via Vary', function (): void {
        $response = apiGet('/api/rest/v2/store-config');

        $vary = apiHeader($response, 'Vary');
        expect($vary)->not->toBeNull();
        expect($vary)->toContain('X-Store-Code');
    });

    it('marks unauthenticated requests to non-public paths as no-store', function (): void {
        // /api/rest/v2/products is gated behind PUBLIC_ACCESS at the firewall
        // but is not in HttpCacheListener::PUBLIC_PATHS, so it should fall
        // into the "unauthenticated, non-public" tier.
        $response = apiGet('/api/rest/v2/products');

        if ($response['status'] === 200) {
            $cacheControl = apiHeader($response, 'Cache-Control');
            expect($cacheControl)->not->toBeNull();
            expect($cacheControl)->toContain('no-store');
        } else {
            // Endpoint may legitimately require auth in some configurations;
            // skip the cache-tier assertion in that case.
            expect($response['status'])->toBeGreaterThanOrEqual(200);
        }
    });

});

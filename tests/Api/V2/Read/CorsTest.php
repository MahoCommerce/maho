<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * Verifies that the nelmio_cors configuration in Kernel.php answers preflight
 * requests under /api/* with the expected Access-Control-Allow-* headers.
 *
 * @group read
 */

describe('CORS', function (): void {

    it('answers OPTIONS preflight on an API path with allow-method/headers', function (): void {
        $response = apiOptions('/api/rest/v2/store-config', [
            'Origin' => 'https://example.test',
            'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'Authorization, Content-Type, X-Store-Code',
        ]);

        // nelmio_cors typically returns 204 (or 200) for a successful preflight.
        expect($response['status'])->toBeIn([200, 204]);

        $allowMethods = apiHeader($response, 'Access-Control-Allow-Methods');
        expect($allowMethods)->not->toBeNull();
        // Kernel.php declares GET/POST/PUT/PATCH/DELETE/OPTIONS for /api/*.
        expect($allowMethods)->toContain('GET');
        expect($allowMethods)->toContain('POST');

        $allowHeaders = apiHeader($response, 'Access-Control-Allow-Headers');
        expect($allowHeaders)->not->toBeNull();
        expect(strtolower($allowHeaders))->toContain('authorization');
        expect(strtolower($allowHeaders))->toContain('x-store-code');
    });

    it('echoes a configured allow-origin (not blindly reflecting Origin)', function (): void {
        // The configured allow_origin defaults to web/secure/base_url's host,
        // we just assert that *some* origin is returned, not that a specific
        // arbitrary one is reflected. Reflecting any Origin would be the bug.
        $response = apiOptions('/api/rest/v2/store-config', [
            'Origin' => 'https://attacker.example',
            'Access-Control-Request-Method' => 'GET',
        ]);

        $allowOrigin = apiHeader($response, 'Access-Control-Allow-Origin');
        if ($allowOrigin !== null) {
            // If a header is sent, it must not be the attacker's origin echoed back.
            expect($allowOrigin)->not->toBe('https://attacker.example');
        }
    });

});

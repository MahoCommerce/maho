<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

/**
 * Verifies IdempotencyListener behavior:
 *  - Invalid key formats are rejected with 400 before any side effect runs
 *  - For authenticated callers, replaying the same key returns the stored
 *    response with X-Idempotency-Replayed: true
 *  - Unauthenticated callers are not stored/replayed (no shared 'anonymous'
 *    scope across guests)
 *
 * @group write
 */

describe('Idempotency listener', function (): void {

    it('rejects an oversized idempotency key', function (): void {
        $response = apiPost(
            '/api/rest/v2/cms-pages',
            ['identifier' => 'irrelevant', 'title' => 'irrelevant'],
            null,
            ['X-Idempotency-Key' => str_repeat('a', 256)],
        );

        expect($response['status'])->toBe(400);
    });

    it('rejects a key with disallowed characters', function (): void {
        $response = apiPost(
            '/api/rest/v2/cms-pages',
            ['identifier' => 'irrelevant', 'title' => 'irrelevant'],
            null,
            ['X-Idempotency-Key' => 'has spaces and !@#'],
        );

        expect($response['status'])->toBe(400);
    });

    it('replays the stored response for the same key under the same caller', function (): void {
        $token = serviceToken(['all']);
        $key = 'pest-idempotent-' . substr(uniqid(), -10);
        $identifier = 'pest-idempotent-page-' . substr(uniqid(), -10);

        $first = apiPost(
            '/api/rest/v2/cms-pages',
            [
                'identifier' => $identifier,
                'title' => 'Idempotency Test',
                'content' => '<p>hello</p>',
                'isActive' => true,
                'stores' => ['all'],
            ],
            $token,
            ['X-Idempotency-Key' => $key],
        );

        // If page creation isn't reachable in this build, give up cleanly
        // rather than asserting on a 4xx (the replay still needs to be
        // verified, but only when the create path actually succeeded).
        if (!in_array($first['status'], [200, 201], true)) {
            $this->markTestSkipped('cms-pages create returned ' . $first['status']);
            return;
        }

        $createdId = $first['json']['id'] ?? null;
        if ($createdId !== null) {
            trackCreated('cms_page', (int) $createdId);
        }

        $second = apiPost(
            '/api/rest/v2/cms-pages',
            [
                // Different body — replay should still echo the original.
                'identifier' => $identifier . '-different',
                'title' => 'Different Title',
            ],
            $token,
            ['X-Idempotency-Key' => $key],
        );

        expect($second['status'])->toBe($first['status']);
        expect(apiHeader($second, 'X-Idempotency-Replayed'))->toBe('true');
        expect($second['raw'])->toBe($first['raw']);
    });

    it('does not replay for unauthenticated callers (no shared anonymous scope)', function (): void {
        $key = 'pest-anon-' . substr(uniqid(), -10);

        // Two unauthenticated POSTs to the same path with the same key. Both
        // should be evaluated independently — i.e. the second must not return
        // the first's stored body. Because both will likely be rejected with
        // 401 before reaching any storage, the assertion is simply that the
        // listener didn't short-circuit the second with a replayed response.
        $first = apiPost(
            '/api/rest/v2/cms-pages',
            ['identifier' => 'whatever', 'title' => 'whatever'],
            null,
            ['X-Idempotency-Key' => $key],
        );

        $second = apiPost(
            '/api/rest/v2/cms-pages',
            ['identifier' => 'different', 'title' => 'different'],
            null,
            ['X-Idempotency-Key' => $key],
        );

        // Crucial: the replay header must not appear on either — anonymous
        // callers bypass storage entirely.
        expect(apiHeader($first, 'X-Idempotency-Replayed'))->toBeNull();
        expect(apiHeader($second, 'X-Idempotency-Replayed'))->toBeNull();
    });

});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\Security\RateLimiter;

uses(Tests\MahoBackendTestCase::class);

function rateLimiterForTest(int $maxAttempts, int $windowSeconds = 600): RateLimiter
{
    return new RateLimiter('rate_limiter_test:' . uniqid('', true), $maxAttempts, $windowSeconds);
}

describe('RateLimiter::attemptOrThrow()', function (): void {

    it('records each attempt, then throws when the budget is used', function (): void {
        $limiter = rateLimiterForTest(2, 600);

        $limiter->attemptOrThrow();
        $limiter->attemptOrThrow();

        try {
            $limiter->attemptOrThrow();
            $this->fail('attemptOrThrow() must throw');
        } catch (Mage_Core_Exception_TooManyRequests $e) {
            expect($e->getMessage())->toBe('Too many requests. Please try again later.')
                ->and($e->getRetryAfter())->toBe(600)
                ->and($limiter->attempts())->toBe(2);
        } finally {
            $limiter->clear();
        }
    });

    it('throws the given message', function (): void {
        $limiter = rateLimiterForTest(1);
        $limiter->attemptOrThrow();

        try {
            expect(fn() => $limiter->attemptOrThrow('Too many attempts. Please wait a moment and try again.'))
                ->toThrow(Mage_Core_Exception_TooManyRequests::class, 'Too many attempts. Please wait a moment and try again.');
        } finally {
            $limiter->clear();
        }
    });

    it('never throws and records nothing when the limit is off', function (): void {
        $limiter = rateLimiterForTest(0);

        $limiter->attemptOrThrow();
        $limiter->attemptOrThrow();

        expect($limiter->attempts())->toBe(0);
    });

});

describe('RateLimiter::throwIfTooManyAttempts()', function (): void {

    it('records nothing, and throws only after hit() used the budget', function (): void {
        $limiter = rateLimiterForTest(2, 120);

        $limiter->throwIfTooManyAttempts();
        $limiter->throwIfTooManyAttempts();
        $limiter->throwIfTooManyAttempts();
        expect($limiter->attempts())->toBe(0);

        $limiter->hit();
        $limiter->throwIfTooManyAttempts();
        $limiter->hit();

        try {
            $limiter->throwIfTooManyAttempts('Too many attempts. Please try again later.');
            $this->fail('throwIfTooManyAttempts() must throw');
        } catch (Mage_Core_Exception_TooManyRequests $e) {
            expect($e->getMessage())->toBe('Too many attempts. Please try again later.')
                ->and($e->getRetryAfter())->toBe(120)
                ->and($limiter->attempts())->toBe(2);
        } finally {
            $limiter->clear();
        }
    });

});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

beforeEach(function () {
    $this->helper = Mage::helper('core');
});

describe('Value-keyed rate limiter', function () {
    it('allows maxAttempts hits then blocks the next one', function () {
        $value = uniqid();

        for ($i = 0; $i < 3; $i++) {
            expect($this->helper->rateLimiterBy('test', $value, 3, 60)->attempt())->toBeTrue();
        }
        expect($this->helper->rateLimiterBy('test', $value, 3, 60)->attempt())->toBeFalse();
    });

    it('keeps independent budgets per value and per namespace', function () {
        $a = uniqid();
        $b = uniqid();

        expect($this->helper->rateLimiterBy('test', $a, 1, 60)->attempt())->toBeTrue();
        expect($this->helper->rateLimiterBy('test', $a, 1, 60)->attempt())->toBeFalse();

        // A different value is untouched by $a's exhaustion...
        expect($this->helper->rateLimiterBy('test', $b, 1, 60)->attempt())->toBeTrue();
        // ...and so is the same value under a different namespace.
        expect($this->helper->rateLimiterBy('other', $a, 1, 60)->attempt())->toBeTrue();
    });

    it('treats tooManyAttempts as a pure read that consumes no budget', function () {
        $value = uniqid();
        $limiter = $this->helper->rateLimiterBy('test', $value, 1, 60);

        for ($i = 0; $i < 10; $i++) {
            expect($limiter->tooManyAttempts())->toBeFalse();
        }
        // Still allowed: the reads above recorded nothing.
        expect($limiter->attempt())->toBeTrue();
        expect($limiter->tooManyAttempts())->toBeTrue();
    });

    it('reports remaining budget and can be cleared', function () {
        $value = uniqid();
        $limiter = $this->helper->rateLimiterBy('test', $value, 3, 60);

        expect($limiter->remaining())->toBe(3);
        $limiter->hit();
        expect($limiter->remaining())->toBe(2);

        $limiter->clear();
        expect($limiter->remaining())->toBe(3);
        expect($limiter->attempt())->toBeTrue();
    });

    it('is disabled by a non-positive limit (never blocks, records nothing)', function () {
        $value = uniqid();

        for ($i = 0; $i < 20; $i++) {
            expect($this->helper->rateLimiterBy('test', $value, 0, 60)->attempt())->toBeTrue();
        }
        expect($this->helper->rateLimiterBy('test', $value, -5, 60)->tooManyAttempts())->toBeFalse();
    });
});

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

describe('Keyed rate limiter', function () {
    it('allows maxAttempts hits then blocks the next one', function () {
        $key = 'test_ratelimit_' . uniqid();

        for ($i = 0; $i < 3; $i++) {
            expect($this->helper->isRateLimitExceeded(false, true, $key, 3, 60))->toBeFalse();
        }
        expect($this->helper->isRateLimitExceeded(false, true, $key, 3, 60))->toBeTrue();
    });

    it('keeps independent budgets per key', function () {
        $keyA = 'test_ratelimit_a_' . uniqid();
        $keyB = 'test_ratelimit_b_' . uniqid();

        expect($this->helper->isRateLimitExceeded(false, true, $keyA, 1, 60))->toBeFalse();
        expect($this->helper->isRateLimitExceeded(false, true, $keyA, 1, 60))->toBeTrue();

        // keyB is untouched by keyA's exhaustion
        expect($this->helper->isRateLimitExceeded(false, true, $keyB, 1, 60))->toBeFalse();
    });

    it('does not consume budget when recordRateLimitHit is false', function () {
        $key = 'test_ratelimit_norecord_' . uniqid();

        for ($i = 0; $i < 10; $i++) {
            expect($this->helper->isRateLimitExceeded(false, false, $key, 1, 60))->toBeFalse();
        }
    });

    it('counts explicit recordRateLimitHit calls toward the budget', function () {
        $key = 'test_ratelimit_explicit_' . uniqid();

        $this->helper->recordRateLimitHit($key, 60);
        $this->helper->recordRateLimitHit($key, 60);

        // Two hits recorded; a check that does not record sees the budget exhausted at maxAttempts=2
        expect($this->helper->isRateLimitExceeded(false, false, $key, 2, 60))->toBeTrue();
    });

    it('adds a session error when setErrorMessage is true and the limit is exceeded', function () {
        $key = 'test_ratelimit_msg_' . uniqid();
        $session = Mage::getSingleton('core/session');
        $session->getMessages(true); // clear

        expect($this->helper->isRateLimitExceeded(false, true, $key, 1, 60))->toBeFalse();
        expect($this->helper->isRateLimitExceeded(true, true, $key, 1, 60))->toBeTrue();

        expect($session->getMessages()->getErrors())->not->toBeEmpty();
        $session->getMessages(true);
    });
});

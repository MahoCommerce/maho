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

    it('adds the default "Too Soon" session error on block, suppressible', function () {
        $key = 'test_ratelimit_msg_' . uniqid();
        $session = Mage::getSingleton('core/session');
        $session->getMessages(true); // clear

        // Exhaust the single-hit budget, staying silent
        expect($this->helper->isRateLimitExceeded(false, true, $key, 1, 60))->toBeFalse();

        // Default-on message: blocked + session error
        expect($this->helper->isRateLimitExceeded(true, true, $key, 1, 60))->toBeTrue();
        expect($session->getMessages()->getErrors())->not->toBeEmpty();
        $session->getMessages(true);

        // Suppressed: blocked but no new error
        expect($this->helper->isRateLimitExceeded(false, true, $key, 1, 60))->toBeTrue();
        expect($session->getMessages()->getErrors())->toBeEmpty();
    });
});

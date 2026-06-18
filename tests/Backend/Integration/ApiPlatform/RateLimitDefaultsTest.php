<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

/*
 * RateLimitTrait::checkRateLimit() reads system/rate_limit/{key} and treats an
 * unset key (which casts to 0) as "disabled". Without shipped defaults the API
 * auth and abuse-prone endpoints would have brute-force / enumeration
 * protection silently turned off out of the box. These defaults keep it on.
 */

it('ships non-zero rate-limit defaults for every throttled API key', function (string $key): void {
    expect((int) Mage::getStoreConfig('system/rate_limit/' . $key))->toBeGreaterThan(0);
})->with([
    'auth_token_ip',
    'customer_login',
    'customer_register',
    'forgot_password',
    'reset_password',
    'newsletter_subscribe',
    'newsletter_unsubscribe',
    'review_submit',
    'contact',
    'coupon_validate',
    'giftcard_balance',
]);

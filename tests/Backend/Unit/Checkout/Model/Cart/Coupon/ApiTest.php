<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('forwards the store when a legacy cart API client adds a coupon', function (): void {
    $api = new class extends Mage_Checkout_Model_Cart_Coupon_Api {
        public array $calls = [];

        protected function _applyCoupon($quoteId, $couponCode, $store = null)
        {
            $this->calls[] = [$quoteId, $couponCode, $store];
            return true;
        }
    };

    expect($api->add(7, 'SAVE10', 2))->toBeTrue()
        ->and($api->calls)->toBe([[7, 'SAVE10', 2]]);
});

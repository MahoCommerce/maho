<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('blocks gift-card balance checks after the limit within the window', function () {
    // The limiter keys by client identity, so clear any carry-over hits for this test client before counting.
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);
    $service = Mage::getService('giftcard/giftcard');
    $websiteId = (int) Mage::app()->getStore()->getWebsiteId();

    for ($i = 0; $i < 10; $i++) {
        expect(fn() => $service->checkBalance('PEST-NO-SUCH-CARD', $websiteId))->toThrow(Mage_Core_Exception_NoSuchEntity::class);
    }
    expect(fn() => $service->checkBalance('PEST-NO-SUCH-CARD', $websiteId))->toThrow(Mage_Core_Exception_TooManyRequests::class);
});

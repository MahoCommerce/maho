<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

it('blocks gift-card balance checks after the limit within the window', function () {
    // The limiter now keys by client identity (not a unique per-run key), so clear any
    // carry-over hits for this test client before counting.
    Mage::app()->cleanCache([\Maho\Security\RateLimiter::CACHE_TAG]);

    $request = new Mage_Core_Controller_Request_Http(
        SymfonyRequest::create('http://localhost/giftcard/cart/checkBalance', 'POST'),
    );
    $response = new Mage_Core_Controller_Response_Http();
    $controller = new Maho_Giftcard_CartController($request, $response);

    $isRateLimited = Closure::bind(
        fn() => $this->_isRateLimited(),
        $controller,
        Maho_Giftcard_CartController::class,
    );

    for ($i = 0; $i < 10; $i++) {
        expect($isRateLimited())->toBeFalse();
    }
    expect($isRateLimited())->toBeTrue();
});

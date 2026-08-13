<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Nothing in the tree ever read the `currency` cookie back, so writing one was
 * cost with no reader. It also reached an unauthenticated API read, because the
 * no-rate fallback ran on every convertPrice() call and wrote from there, and a
 * response carrying Set-Cookie is one no shared cache will store.
 */

function spyOnCookies(): object
{
    $spy = new class extends Mage_Core_Model_Cookie {
        /** @var list<string> */
        public array $names = [];

        #[\Override]
        public function set($name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
        {
            $this->names[] = $name;
            return $this;
        }

        #[\Override]
        public function delete($name, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
        {
            $this->names[] = $name;
            return $this;
        }
    };

    Mage::unregister('_singleton/core/cookie');
    Mage::register('_singleton/core/cookie', $spy);

    return $spy;
}

describe('Store currency cookie', function (): void {

    afterEach(function (): void {
        Mage::unregister('_singleton/core/cookie');
        resetCurrencyState();
    });

    test('switching the display currency writes no cookie', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');
        $spy = spyOnCookies();

        $store->setCurrentCurrencyCode('EUR');

        expect($store->getCurrentCurrencyCode())->toBe('EUR');
        // Named, not empty: the session may legitimately write a cookie of its own.
        expect($spy->names)->not->toContain('currency');
    });

    test('switching back to the default writes no cookie either', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');
        $store->setCurrentCurrencyCode('EUR');
        $spy = spyOnCookies();

        $store->setCurrentCurrencyCode('USD');

        expect($store->getCurrentCurrencyCode())->toBe('USD');
        expect($spy->names)->not->toContain('currency');
    });

    test('the no-rate fallback writes no cookie', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');
        $spy = spyOnCookies();

        // The read an anonymous API GET performs: the fallback fires here.
        expect($store->getCurrentCurrencyCode())->toBe('USD');
        expect((float) $store->convertPrice(10.0, false))->toBe(10.0);

        expect($spy->names)->not->toContain('currency');
    });

});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * getCurrentCurrency() falls back to base when the display currency has no
 * imported rate. That fallback must not write the currency cookie.
 */

class RecordingCookie extends Mage_Core_Model_Cookie
{
    /** @var list<string> */
    public array $writes = [];

    #[\Override]
    public function set($name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        $this->writes[] = (string) $name;
        return $this;
    }
}

function storeFallbackConfigure(string $display, string $allowed): Mage_Core_Model_Store
{
    if (Mage::app()->getStore(1)->getBaseCurrencyCode() !== 'USD') {
        test()->markTestSkipped('Test expects USD base currency on store 1');
    }

    return setStoreDisplayCurrency($display, $allowed);
}

describe('Store currency fallback cookie', function (): void {

    beforeEach(function (): void {
        $this->cookie = new RecordingCookie();
        Mage::unregister('_singleton/core/cookie');
        Mage::register('_singleton/core/cookie', $this->cookie);
    });

    afterEach(function (): void {
        clearStoreSessionCurrency();
    });

    test('the no-rate fallback does not write the currency cookie', function (): void {
        $store = storeFallbackConfigure('GBP', 'USD,GBP');

        if ((float) $store->getBaseCurrency()->getRate('GBP') > 0) {
            test()->markTestSkipped('This install has a USD to GBP rate, so there is no fallback to observe');
        }

        expect($store->getCurrentCurrencyCode())->toBe('GBP');
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');

        expect($this->cookie->writes)->not->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

    test('the fallback is memoised on the instance, so prices convert at parity', function (): void {
        $store = storeFallbackConfigure('GBP', 'USD,GBP');

        if ((float) $store->getBaseCurrency()->getRate('GBP') > 0) {
            test()->markTestSkipped('This install has a USD to GBP rate, so there is no fallback to observe');
        }

        $resolved = $store->getCurrentCurrency();
        expect($resolved->getCode())->toBe('USD');

        expect($store->getCurrentCurrency())->toBe($resolved);
        expect((float) $store->convertPrice(10.0, false))->toEqualWithDelta(10.0, 0.001);

        expect($this->cookie->writes)->not->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

    test('an explicit currency switch still writes the cookie', function (): void {
        $store = storeFallbackConfigure('USD', 'USD,EUR');

        if ((float) $store->getBaseCurrency()->getRate('EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $store->setCurrentCurrencyCode('EUR');

        expect($this->cookie->writes)->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

});

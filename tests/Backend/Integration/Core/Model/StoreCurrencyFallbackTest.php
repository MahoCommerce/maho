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

describe('Store currency fallback', function (): void {

    beforeEach(function (): void {
        $this->cookie = new RecordingCookie();
        Mage::unregister('_singleton/core/cookie');
        Mage::register('_singleton/core/cookie', $this->cookie);
    });

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('the no-rate fallback does not write the currency cookie', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        expect($store->getCurrentCurrency()->getCode())->toBe('USD');

        expect($this->cookie->writes)->not->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

    test('the fallback is memoised on the instance, so prices convert at parity', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        $resolved = $store->getCurrentCurrency();
        expect($resolved->getCode())->toBe('USD');

        expect($store->getCurrentCurrency())->toBe($resolved);
        expect((float) $store->convertPrice(10.0, false))->toEqualWithDelta(10.0, 0.001);

        expect($this->cookie->writes)->not->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

    test('an explicit currency switch still writes the cookie', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        if ((float) $store->getBaseCurrency()->getRate('EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $store->setCurrentCurrencyCode('EUR');

        expect($this->cookie->writes)->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

});

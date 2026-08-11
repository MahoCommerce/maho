<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * getCurrentCurrency() falls back to base when the display currency has no
 * imported rate. The code accessor must report that same fallback, and the
 * fallback must not write the currency cookie or overwrite the shopper's
 * chosen currency.
 */

class RecordingCookie extends Mage_Core_Model_Cookie
{
    /** @var list<string> */
    public array $writes = [];

    /** @var list<string> */
    public array $deletes = [];

    #[\Override]
    public function set($name, $value, $period = null, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        $this->writes[] = (string) $name;
        return $this;
    }

    #[\Override]
    public function delete($name, $path = null, $domain = null, $secure = null, $httponly = null, $sameSite = null)
    {
        $this->deletes[] = (string) $name;
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

    test('the code accessor reports the fallback currency, whichever is read first', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        // Read the code before anything has resolved the currency object: the
        // two must still agree, or a consumer that only reads the code labels
        // base-converted amounts with the currency that has no rate.
        expect($store->getCurrentCurrencyCode())->toBe('USD');
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');
    });

    test('the code accessor reports the fallback without a session to lean on', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        // The API has no store session: init() gets no session name and start()
        // returns early, so nothing written there survives. Drop the session
        // object and the memo to model a fresh stateless request.
        $session = new ReflectionProperty(Mage_Core_Model_Store::class, '_session');
        $session->setValue($store, null);
        $store->unsetData('current_currency');

        expect($store->getCurrentCurrencyCode())->toBe('USD');
    });

    test('the fallback leaves an explicit currency choice intact', function (): void {
        $store = useNoRateDisplayCurrency('GBP', 'USD,GBP');

        // The shopper picked GBP; the rate is missing today. Resolving must not
        // rewrite their choice, or importing the rate later would not restore it.
        $store->setCurrentCurrencyCode('GBP');
        expect($store->getCurrentCurrency()->getCode())->toBe('USD');

        expect($_SESSION['store_' . $store->getCode()]['currency_code'] ?? null)->toBe('GBP');
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

    test('switching back to the default currency clears the cookie', function (): void {
        requireUsdBaseStore();
        $store = setStoreDisplayCurrency('USD', 'USD,EUR');

        if ((float) $store->getBaseCurrency()->getRate('EUR') <= 0) {
            test()->markTestSkipped('USD to EUR rate not available');
        }

        $store->setCurrentCurrencyCode('EUR');
        $store->setCurrentCurrencyCode('USD');

        // Otherwise a year-long cookie keeps advertising a currency the shopper
        // has already switched away from.
        expect($this->cookie->deletes)->toContain(Mage_Core_Model_Store::COOKIE_CURRENCY);
    });

});

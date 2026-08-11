<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Maho\ApiPlatform\EventListener\CurrencyContextListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

uses(Tests\MahoBackendTestCase::class);

/**
 * A store view may allow several display currencies; X-Currency-Code picks
 * among them, the way the storefront's switcher does.
 */

function dispatchCurrencyHeader(?string $code): void
{
    $request = Request::create('http://localhost/api/rest/v2/products');
    if ($code !== null) {
        $request->headers->set(CurrencyContextListener::HEADER, $code);
    }

    $kernel = new class implements HttpKernelInterface {
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): \Symfony\Component\HttpFoundation\Response
        {
            return new \Symfony\Component\HttpFoundation\Response();
        }
    };

    (new CurrencyContextListener())(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
}

describe('X-Currency-Code', function (): void {

    beforeEach(function (): void {
        requireUsdBaseStore();
        $this->store = setStoreDisplayCurrency('USD', 'USD,EUR');
        Mage::app()->setCurrentStore(1);

        $this->rate = (float) $this->store->getBaseCurrency()->getRate('EUR');
        if ($this->rate <= 0 || $this->rate == 1.0) {
            test()->markTestSkipped('USD to EUR rate not available or trivially 1');
        }
    });

    afterEach(function (): void {
        resetCurrencyState();
    });

    test('an allowed currency applies to the request', function (): void {
        expect($this->store->getCurrentCurrencyCode())->toBe('USD');

        dispatchCurrencyHeader('EUR');

        expect($this->store->getCurrentCurrencyCode())->toBe('EUR');
        expect((float) $this->store->convertPrice(10.0, false))
            ->toEqualWithDelta(10.0 * $this->rate, 0.011);
    });

    test('it does not record the currency as an explicit choice', function (): void {
        dispatchCurrencyHeader('EUR');

        // A header is per request. Recording it would leak one caller's choice
        // into the next request on the same session.
        expect($_SESSION['store_' . $this->store->getCode()]['currency_code'] ?? null)->toBeNull();
    });

    test('a currency outside the allowed set is refused', function (): void {
        expect(fn() => dispatchCurrencyHeader('JPY'))
            ->toThrow(BadRequestHttpException::class);

        // Refused, not silently served in base under a JPY label.
        expect($this->store->getCurrentCurrencyCode())->toBe('USD');
    });

    test('an empty header is refused', function (): void {
        expect(fn() => dispatchCurrencyHeader(' '))
            ->toThrow(BadRequestHttpException::class);
    });

    test('no header leaves the store on its configured currency', function (): void {
        dispatchCurrencyHeader(null);

        expect($this->store->getCurrentCurrencyCode())->toBe('USD');
    });

    test('the case of the header value does not matter', function (): void {
        dispatchCurrencyHeader('eur');

        expect($this->store->getCurrentCurrencyCode())->toBe('EUR');
    });

});

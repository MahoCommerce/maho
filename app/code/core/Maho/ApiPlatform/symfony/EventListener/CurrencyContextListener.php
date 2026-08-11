<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Honors the `X-Currency-Code` header, letting a caller pick among the display
 * currencies a store view allows, the way the storefront's currency switcher
 * does.
 *
 * Header only, no query parameter: `Vary` cannot express a query parameter, and
 * HttpCacheListener already downgrades those responses to `private` for exactly
 * that reason, which would make every priced read uncacheable by a CDN.
 *
 * Priority 109 sits below StoreContextListener (110), since the set of allowed
 * currencies is per store, and above AdminBridgeListener (105) and
 * IdempotencyListener (100).
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 109)]
class CurrencyContextListener
{
    public const HEADER = 'X-Currency-Code';
    public const ATTR_REQUESTED_CURRENCY_CODE = '_maho_requested_currency_code';

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $code = $request->headers->get(self::HEADER);
        if ($code === null) {
            return;
        }

        $code = strtoupper(trim($code));
        if ($code === '') {
            throw new BadRequestHttpException('The ' . self::HEADER . ' header cannot be empty.');
        }

        $store = StoreContext::getStore();

        // Refuse rather than serve base prices under a label the caller did not
        // ask for. Mirrors how an unknown store is rejected, and matches the
        // storefront, which validates the switch against the same set.
        if (!in_array($code, $store->getAvailableCurrencyCodes(true), true)) {
            throw new BadRequestHttpException("Currency not available for this store: {$code}");
        }

        // Without a rate the store would silently fall back to base, so the
        // response would not be in the currency that was asked for.
        if ($code !== $store->getBaseCurrencyCode()
            && (float) $store->getBaseCurrency()->getRate($code) <= 0) {
            throw new BadRequestHttpException("No exchange rate available for: {$code}");
        }

        // A header applies to this request alone; there is no session to record it in.
        $store->setCurrentCurrencyCode($code, persist: false);
        $request->attributes->set(self::ATTR_REQUESTED_CURRENCY_CODE, $code);
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Directory
 */

declare(strict_types=1);

namespace Mage\Directory\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;

class CurrencyProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        StoreContext::ensureStore();
        $store = StoreContext::getStore();

        $baseCurrency = $store->getBaseCurrency();
        $allowedCurrencies = $store->getAvailableCurrencyCodes(true);
        $rates = $baseCurrency->getCurrencyRates($baseCurrency, $allowedCurrencies);

        $currencies = [];
        foreach ($allowedCurrencies as $currencyCode) {
            // MySQL and PostgreSQL return the DECIMAL rate as a string, which strict_types
            // refuses to assign to ?float. SQLite returns a number, so this never fails locally.
            $rate = isset($rates[$currencyCode]) ? (float) $rates[$currencyCode] : null;

            // Offer only what can actually be served: without a rate the store
            // falls back to base, so listing it would advertise a currency
            // X-Currency-Code then refuses. The storefront switcher omits these
            // for the same reason (Mage_Directory_Block_Currency).
            if ($currencyCode !== $baseCurrency->getCode() && ($rate === null || $rate <= 0)) {
                continue;
            }

            $currency = \Mage::getModel('directory/currency')->load($currencyCode);

            $dto = Currency::fromModel($currency);
            $dto->symbol = $currency->getCurrencySymbol();
            $dto->exchangeRate = $rate;
            $currencies[] = $dto;
        }

        return $currencies;
    }
}

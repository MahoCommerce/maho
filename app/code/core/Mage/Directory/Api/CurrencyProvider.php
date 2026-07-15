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
            $currency = \Mage::getModel('directory/currency')->load($currencyCode);

            $dto = Currency::fromModel($currency);
            $dto->symbol = $currency->getCurrencySymbol();
            $dto->exchangeRate = $rates[$currencyCode] ?? null;
            $currencies[] = $dto;
        }

        return $currencies;
    }
}

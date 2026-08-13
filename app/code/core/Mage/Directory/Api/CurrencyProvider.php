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

        $currencies = [];

        // Offer only what can actually be served: listing a currency the store
        // would fall back out of advertises one X-Currency-Code then refuses.
        foreach ($store->getServeableCurrencyRates() as $currencyCode => $rate) {
            $currency = \Mage::getModel('directory/currency')->load($currencyCode);

            $dto = Currency::fromModel($currency);
            $dto->symbol = $currency->getCurrencySymbol();
            $dto->exchangeRate = $rate;
            $currencies[] = $dto;
        }

        return $currencies;
    }
}

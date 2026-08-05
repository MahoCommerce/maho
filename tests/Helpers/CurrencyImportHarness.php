<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Exposes an EUR-based importer's collaborators to tests: the HTTP client, and the currency
 * lists that otherwise come from the store's currency configuration.
 */
trait CurrencyImportHarness
{
    private array $allowedCurrencies = ['EUR', 'GBP', 'USD'];

    private array $baseCurrencies = ['USD'];

    public function setHttpClient(HttpClientInterface $httpClient): static
    {
        $this->_httpClient = $httpClient;
        return $this;
    }

    public function setCurrencies(array $allowed, array $base): static
    {
        $this->allowedCurrencies = $allowed;
        $this->baseCurrencies = $base;
        return $this;
    }

    public function getUrlTemplate(): string
    {
        return $this->_url;
    }

    #[\Override]
    protected function _getCurrencyCodes()
    {
        return $this->allowedCurrencies;
    }

    #[\Override]
    protected function _getDefaultCurrencyCodes()
    {
        return $this->baseCurrencies;
    }
}

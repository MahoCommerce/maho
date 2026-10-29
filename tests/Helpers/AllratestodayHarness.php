<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Mage_Directory_Model_Currency_Import_Allratestoday;

class AllratestodayHarness extends Mage_Directory_Model_Currency_Import_Allratestoday
{
    use CurrencyImportHarness;

    private string $bank = '';

    private string $apiKey = '';

    public function setBank(string $bank): static
    {
        $this->bank = $bank;
        return $this;
    }

    public function setApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    #[\Override]
    protected function _getBank(): string
    {
        return strtolower(trim($this->bank));
    }

    #[\Override]
    protected function _getApiKey(): string
    {
        return trim($this->apiKey);
    }
}

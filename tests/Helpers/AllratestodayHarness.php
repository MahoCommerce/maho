<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Mage;
use Mage_Directory_Model_Currency_Import_Allratestoday;

class AllratestodayHarness extends Mage_Directory_Model_Currency_Import_Allratestoday
{
    use CurrencyImportHarness;

    public function __construct()
    {
        parent::__construct();
        $this->setBank('');
        $this->setApiKey('');
    }

    /**
     * Store the code the way the admin field does, so reads use the real config path.
     */
    public function setBank(string $bank): static
    {
        Mage::app()->getStore()->setConfig(self::XML_PATH_ALLRATESTODAY_BANK, $bank);
        return $this;
    }

    /**
     * Store the key encrypted, the way the admin field does, so reads use the real decryption.
     */
    public function setApiKey(string $apiKey): static
    {
        $value = $apiKey === '' ? '' : Mage::helper('core')->encrypt($apiKey);
        Mage::app()->getStore()->setConfig(self::XML_PATH_ALLRATESTODAY_API_KEY, $value);
        return $this;
    }
}

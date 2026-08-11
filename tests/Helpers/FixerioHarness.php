<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Mage;
use Mage_Directory_Model_Currency_Import_Fixerio;

class FixerioHarness extends Mage_Directory_Model_Currency_Import_Fixerio
{
    use CurrencyImportHarness;

    /**
     * Store the key the way the admin field does, encrypted, so reads exercise the real
     * decryption path.
     */
    public static function storeApiKey(string $apiKey): void
    {
        $value = $apiKey === '' ? '' : Mage::helper('core')->encrypt($apiKey);
        Mage::app()->getStore()->setConfig(self::XML_PATH_FIXERIO_API_KEY, $value);
    }
}

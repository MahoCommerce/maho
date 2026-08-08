<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Tests\Helpers;

use Mage_Directory_Model_Currency_Import_Exchangerateapi;

class ExchangerateapiHarness extends Mage_Directory_Model_Currency_Import_Exchangerateapi
{
    use CurrencyImportHarness;
}

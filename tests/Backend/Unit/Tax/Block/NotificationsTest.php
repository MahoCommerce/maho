<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('links the tax notification to the user guide page that explains rounding', function () {
    $url = Mage::getStoreConfig(Mage_Tax_Model_Config::XML_PATH_TAX_NOTIFICATION_URL);

    expect($url)->toBe('https://mahocommerce.com/user-guide/tax-calculation/')
        ->and(file_exists(Mage::getModuleDir('', 'Mage_Tax') . '/docs/user/tax-calculation.md'))->toBeTrue();
});

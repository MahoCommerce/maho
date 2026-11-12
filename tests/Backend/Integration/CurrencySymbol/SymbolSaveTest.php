<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * System > Manage Currency > Symbols saves through Mage_Core_Service_Config, so the side effects of a
 * configuration save are the same as in System > Configuration (#1348).
 */

class SymbolSaveSpyConfigService extends Mage_Core_Service_Config
{
    /** @var list<string> */
    public array $sections = [];

    #[\Override]
    protected function afterChange(string $section, ?string $website, ?string $store): void
    {
        $this->sections[] = $section;
        parent::afterChange($section, $website, $store);
    }
}

beforeEach(function (): void {
    $this->spy = new SymbolSaveSpyConfigService();
    Mage::unregister('_service/core/config');
    Mage::register('_service/core/config', $this->spy);
});

afterEach(function (): void {
    Mage::unregister('_service/core/config');
    Mage::getConfig()->deleteConfig(Mage_CurrencySymbol_Model_System_Currencysymbol::XML_PATH_CUSTOM_CURRENCY_SYMBOL);
    Mage::getConfig()->reinit();
    Mage::app()->reinitStores();
});

it('saves and removes the custom symbols through the configuration service', function (): void {
    $path = Mage_CurrencySymbol_Model_System_Currencysymbol::XML_PATH_CUSTOM_CURRENCY_SYMBOL;

    Mage::getModel('currencysymbol/system_currencysymbol')->setCurrencySymbolsData(['USD' => 'US$']);

    expect($this->spy->sections)->toBe(['currency'])
        ->and(Mage::helper('core')->jsonDecode((string) Mage::getStoreConfig($path, Mage_Core_Model_App::ADMIN_STORE_ID)))->toBe(['USD' => 'US$']);

    Mage::getModel('currencysymbol/system_currencysymbol')->setCurrencySymbolsData([]);

    expect($this->spy->sections)->toBe(['currency', 'currency'])
        ->and(Mage::getConfig()->getConfig($path, 'default', 0))->toBeFalse();
});

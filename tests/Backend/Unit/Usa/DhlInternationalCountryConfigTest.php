<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('validates a DHL shipment using the shipped country configuration', function (bool $resolvePackage): void {
    $store = Mage::app()->getDefaultStoreView();
    $store->setConfig(Mage_Shipping_Model_Shipping::XML_PATH_STORE_COUNTRY_ID, 'DE');
    $product = new Mage_Catalog_Model_Product(['type_id' => 'simple']);
    $item = new Mage_Sales_Model_Quote_Item(['product' => $product]);
    $request = new Mage_Shipping_Model_Rate_Request()->setStoreId($store->getId())->setAllItems([$item]);
    $carrier = new Mage_Usa_Model_Shipping_Carrier_Dhl_International();

    $options = Mage::getConfig()->getOptions();
    $codeDirectory = $options->getCodeDir();
    $workingDirectory = getcwd() ?: BP;
    if ($resolvePackage) {
        $options->setData('code_dir', 'app/code');
        chdir(__DIR__);
    }

    try {
        $path = Mage::getModuleDir('etc', 'Mage_Usa') . '/dhl/international/countries.xml';
        expect(is_file($path))->toBe(!$resolvePackage)
            ->and($carrier->proccessAdditionalValidation($request))->toBe($carrier);

        $readCountry = new ReflectionMethod($carrier, 'getCountryParams');
        $germany = $readCountry->invoke($carrier, 'DE');
        $unitedStates = $readCountry->invoke($carrier, 'US');
        expect($germany->getData('weight_unit'))->toBe('KG')
            ->and($germany->getData('measure_unit'))->toBe('CM')
            ->and($unitedStates->getData('weight_unit'))->toBe('LB')
            ->and($unitedStates->getData('measure_unit'))->toBe('IN')
            ->and($readCountry->invoke($carrier, 'ZZ')->getData())->toBe([]);
    } finally {
        chdir($workingDirectory);
        $options->setData('code_dir', $codeDirectory);
    }
})->with([
    'source checkout' => [false],
    'package resolution' => [true],
]);

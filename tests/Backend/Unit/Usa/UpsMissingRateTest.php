<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * An allowed response currency without a rate used to price a UPS method at zero and offer it.
 * Now the method is dropped, and the reason has to travel out as the error title: when nothing
 * survives conversion the shopper is told why, instead of the carrier vanishing.
 *
 * The response currency is an ISO 4217 "X" code no real currency uses, so no install has a rate
 * for it.
 */

/**
 * @return array{prices: array<string, float>, errorTitle: string}
 */
function upsRestRateForItem(array $shipElement, array $allowedCurrencies): array
{
    $carrier = Mage::getModel('usa/shipping_carrier_ups');

    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setBaseCurrency(Mage::app()->getStore()->getBaseCurrency());
    $request->setPackageCurrency(Mage::app()->getStore()->getCurrentCurrency());
    (new ReflectionProperty($carrier, '_request'))->setValue($carrier, $request);

    $costArr = [];
    $priceArr = [];
    $errorTitle = '';
    $args = [$shipElement, ['03'], $allowedCurrencies, &$costArr, &$priceArr, false, &$errorTitle];
    (new ReflectionMethod($carrier, 'processShippingRestRateForItem'))->invokeArgs($carrier, $args);

    return ['prices' => $priceArr, 'errorTitle' => $errorTitle];
}

it('drops a method it cannot convert and says why', function () {
    $result = upsRestRateForItem([
        'Service' => ['Code' => '03'],
        'TotalCharges' => ['MonetaryValue' => '10.00', 'CurrencyCode' => 'XTN'],
    ], ['XTN']);

    expect($result['prices'])->toBe([])
        ->and($result['errorTitle'])->toContain('XTN');
});

it('offers a method quoted in a currency it can convert', function () {
    $base = Mage::app()->getStore()->getBaseCurrencyCode();

    $result = upsRestRateForItem([
        'Service' => ['Code' => '03'],
        'TotalCharges' => ['MonetaryValue' => '10.00', 'CurrencyCode' => $base],
    ], [$base]);

    expect($result['prices'])->toHaveKey('03')
        ->and($result['errorTitle'])->toBe('');
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/*
 * A UPS method quoted in a currency with no rate is dropped, with the reason as the error title.
 * XTN is an ISO 4217 "X" code no real currency uses, so no install has a rate for it.
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

// The tests below run through setRatePriceData(), where the merchant's general message
// (non-empty on every install) used to overwrite the reason.
function upsRestErrorMessage(string $responseDescription, string $currencyCode): string
{
    $carrier = Mage::getModel('usa/shipping_carrier_ups');

    $request = new Mage_Shipping_Model_Rate_Request();
    $request->setBaseCurrency(Mage::app()->getStore()->getBaseCurrency());
    $request->setPackageCurrency(Mage::app()->getStore()->getCurrentCurrency());
    (new ReflectionProperty($carrier, '_request'))->setValue($carrier, $request);

    $response = (string) json_encode([
        'RateResponse' => [
            'Response' => ['ResponseStatus' => ['Description' => $responseDescription]],
            'RatedShipment' => [
                'Service' => ['Code' => '03'],
                'TotalCharges' => ['MonetaryValue' => '10.00', 'CurrencyCode' => $currencyCode],
            ],
        ],
    ]);

    /** @var Mage_Shipping_Model_Rate_Result $result */
    $result = (new ReflectionMethod($carrier, '_parseRestResponse'))->invoke($carrier, $response);
    $rates = $result->getAllRates();

    return $rates === [] ? '' : (string) $rates[0]->getErrorMessage();
}

it('tells the shopper the reason rather than the merchant s general message', function () {
    $message = upsRestErrorMessage('Success', 'XTN');

    expect($message)->toContain('XTN')
        ->and($message)->not->toBe(Mage::getModel('usa/shipping_carrier_ups')->getConfigData('specificerrmsg'));
});

it('keeps the merchant s message for a response that failed', function () {
    expect(upsRestErrorMessage('Failure', 'XTN'))
        ->toBe(Mage::getModel('usa/shipping_carrier_ups')->getConfigData('specificerrmsg'));
});

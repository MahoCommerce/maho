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

/*
 * The two above stop at the private method that works the reason out. What the shopper is handed
 * comes from setRatePriceData(), where the merchant's general message, non-empty on every install
 * (Usa/etc/config.xml:126), used to overwrite whatever reason arrived. These end there instead.
 */
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

// The merchant's message stays in charge of what the carrier itself said, which is what it was
// configured to keep away from the shopper.
it('keeps the merchant s message for a response that failed', function () {
    expect(upsRestErrorMessage('Failure', 'XTN'))
        ->toBe(Mage::getModel('usa/shipping_carrier_ups')->getConfigData('specificerrmsg'));
});

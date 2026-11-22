<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

use Tests\Helpers\PackageCarrierStub;
use Tests\Helpers\ShippingStub;

uses(Tests\MahoBackendTestCase::class);

/**
 * Build the answer of the carrier for one package.
 * false: the carrier declines. A string: the carrier returns only this error.
 * An array: 'ground' => [price, cost] or [price, cost, carrier] adds a method,
 * 'error' => 'message' adds an error, 'error:one' => 'message' adds an error of carrier 'one',
 * and 'error' => true sets only the error flag.
 */
function packageAnswer(array|string|false $answer): Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Rate_Result_Error|false
{
    if ($answer === false) {
        return false;
    }
    if (is_string($answer)) {
        return new Mage_Shipping_Model_Rate_Result_Error()->setCarrier('test')->setErrorMessage($answer);
    }
    $result = new Mage_Shipping_Model_Rate_Result();
    foreach ($answer as $code => $value) {
        if (str_starts_with($code, 'error')) {
            $carrier = explode(':', $code)[1] ?? 'test';
            $value === true
                ? $result->setError(true)
                : $result->append(new Mage_Shipping_Model_Rate_Result_Error()->setCarrier($carrier)->setErrorMessage($value));
            continue;
        }
        [$price, $cost, $carrier] = $value + [2 => 'test'];
        $method = new Mage_Shipping_Model_Rate_Result_Method()->setCarrier($carrier)->setMethod($code)->setData('cost', $cost);
        if ($price !== null) {
            $method->setPrice($price);
        }
        $result->append($method);
    }
    return $result;
}

it('offers only the methods of every package, at the price and cost of all packages', function (
    array $packages,
    array $answers,
    bool $showErrors,
    array $expected,
): void {
    $carrier = new PackageCarrierStub(fn(float $weight) => packageAnswer($answers[(int) $weight]), showErrors: $showErrors);
    $shipping = new ShippingStub(['test' => $carrier], $packages);

    $shipping->collectCarrierRates('test', new Mage_Shipping_Model_Rate_Request());

    $rates = [];
    foreach ($shipping->getResult()->getAllRates() as $rate) {
        $rates[] = $rate instanceof Mage_Shipping_Model_Rate_Result_Error
            ? $rate->getErrorMessage()
            : [$rate->getMethod(), $rate->getPrice(), $rate->getCost()];
    }
    expect($carrier->getRequestedWeights())->toBe(array_map(floatval(...), array_keys($answers)))
        ->and($rates)->toBe($expected);
})->with([
    'adds the packages and drops a method that one package lacks' => [
        [70 => 2, 5 => 1],
        [
            70 => ['ground' => [4.0, 3.0], 'free' => [0.0, 1.0], 'express' => [3.0, 2.0], 'freight' => [1.0, 1.0]],
            5 => ['express' => [4.0, 5.0], 'ground' => [2.0, 1.0], 'free' => [0.0, 2.0], 'letter' => [1.0, 1.0]],
        ],
        false,
        [['free', 0.0, 4.0], ['ground', 10.0, 7.0], ['express', 10.0, 9.0]],
    ],
    'identical packages share one request, and their price and cost are multiplied' => [
        [70 => 2], [70 => ['ground' => [4.0, 3.0]]], false, [['ground', 8.0, 6.0]],
    ],
    'identical packages keep their error' => [
        [70 => 2], [70 => ['ground' => [4.0, 0.0], 'error' => 'Package unavailable']], true, ['Package unavailable', ['ground', 8.0, 0.0]],
    ],
    'no method is common to every package, and the error stays' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0], 'error' => 'Package unavailable'], 5 => ['letter' => [1.0, 1.0]]], true, ['Package unavailable'],
    ],
    'a package with no method removes every method' => [[70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => []], true, []],
    'the same method code of another carrier does not match' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0, 'one']], 5 => ['ground' => [2.0, 1.0, 'two']]], false, [],
    ],
    'the cost is unknown when one package has no numeric cost' => [
        [70 => 2, 5 => 1],
        [
            70 => ['ground' => [4.0, '3.5'], 'express' => [5.0, ''], 'priority' => [7.0, 1.0]],
            5 => ['ground' => [2.0, '1.5'], 'express' => [3.0, 2.0], 'priority' => [1.0, null]],
        ],
        false,
        [['ground', 10.0, 8.5], ['express', 13.0, null], ['priority', 15.0, null]],
    ],
    'a method without a price on one package is dropped, a free method stays' => [
        [70 => 1, 5 => 2],
        [70 => ['ground' => [4.0, 3.0], 'free' => [0.0, 0.0]], 5 => ['ground' => [null, 1.0], 'free' => [0.0, 0.0]]],
        false,
        [['free', 0.0, 0.0]],
    ],
    'the carrier declines a later package' => [[70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => false], true, []],
    'the carrier declines the first package and gets no more requests' => [[5 => 1, 70 => 1], [5 => false], true, []],
    'an error of one package is shown with the common methods' => [
        [5 => 1, 70 => 1],
        [5 => ['ground' => [2.0, 1.0], 'error' => 'Package unavailable'], 70 => ['ground' => [4.0, 3.0]]],
        true,
        ['Package unavailable', ['ground', 6.0, 4.0]],
    ],
    'an error of one package hides the carrier when errors are hidden' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'error' => 'Package unavailable']], false, [],
    ],
    'an error flag of one package hides the carrier when errors are hidden' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'error' => true]], false, [],
    ],
    'an error flag without a message adds no error to the list' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'error' => true]], true, [['ground', 6.0, 4.0]],
    ],
    'a package that returns only an error is shown' => [
        [70 => 1, 5 => 2], [70 => ['ground' => [4.0, 3.0]], 5 => 'Package unavailable'], true, ['Package unavailable'],
    ],
    'a package that returns only an error is hidden' => [
        [70 => 1, 5 => 2], [70 => ['ground' => [4.0, 3.0]], 5 => 'Package unavailable'], false, [],
    ],
    'the same error of several packages is shown once for each carrier' => [
        [70 => 1, 20 => 1, 5 => 1],
        [70 => ['error:one' => 'Limit applies', 'error:two' => 'Limit applies'], 20 => ['error:one' => 'Limit applies'], 5 => ['error:one' => 'Address unavailable']],
        true,
        ['Limit applies', 'Limit applies', 'Address unavailable'],
    ],
    'a single package keeps the result of the carrier unchanged' => [[70 => 1], [70 => ['ground' => [null, 3.0]]], false, [['ground', null, 3.0]]],
    'a single package keeps its error' => [
        [70 => 1], [70 => ['ground' => [4.0, 3.0], 'error' => 'Package unavailable']], true, ['Package unavailable', ['ground', 4.0, 3.0]],
    ],
    'a single package with an error is hidden when errors are hidden' => [
        [70 => 1], [70 => ['ground' => [4.0, 3.0], 'error' => 'Package unavailable']], false, [],
    ],
]);

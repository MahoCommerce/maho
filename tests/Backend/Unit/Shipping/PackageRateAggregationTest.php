<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('quotes only methods available for every package at their combined price and cost', function (
    array $packages,
    array $responses,
    bool $showErrors,
    array $expected,
    bool $customCostMultiplier = false,
): void {
    $carrier = new class ($responses, $showErrors, $customCostMultiplier) extends Mage_Shipping_Model_Carrier_Abstract {
        /** @var ArrayObject<int, float> */
        public ArrayObject $weights;

        public function __construct(
            private readonly array $responses,
            private readonly bool $showErrors,
            private readonly bool $customCostMultiplier,
        ) {
            parent::__construct();
            $this->weights = new ArrayObject();
        }

        #[\Override]
        public function getConfigData(mixed $field): int
        {
            return match ($field) {
                'shipment_requesttype' => 1,
                'showmethod' => (int) $this->showErrors,
                default => 0,
            };
        }

        #[\Override]
        public function checkAvailableShipCountries(Mage_Shipping_Model_Rate_Request $request): true
        {
            return true;
        }

        #[\Override]
        public function proccessAdditionalValidation(Mage_Shipping_Model_Rate_Request $request): true
        {
            return true;
        }

        #[\Override]
        public function collectRates(Mage_Shipping_Model_Rate_Request $request): Mage_Shipping_Model_Rate_Result|Mage_Shipping_Model_Rate_Result_Error|false|null
        {
            $weight = $request->getPackageWeight();
            $this->weights->append($weight);
            $response = $this->responses[(int) $weight];
            if ($response === false || $response === null) {
                return $response;
            }

            $result = new class ($this->customCostMultiplier) extends Mage_Shipping_Model_Rate_Result {
                public function __construct(private readonly bool $customCostMultiplier) {}

                #[\Override]
                public function updateRatePrice(mixed $packageCount): static
                {
                    if ($this->customCostMultiplier) {
                        foreach ($this->getAllRates() as $rate) {
                            $cost = $rate->getCost();
                            if (is_numeric($cost)) {
                                $rate->setCost((float) $cost * $packageCount);
                            }
                        }
                    }
                    parent::updateRatePrice($packageCount);
                    return $this;
                }
            };
            $error = new Mage_Shipping_Model_Rate_Result_Error()->setErrorMessage('Package unavailable');
            if (is_string($response)) {
                return $response === 'error' ? $error : $result->append($error);
            }
            foreach ($response as $code => $quote) {
                if ($quote === true) {
                    $result->setError(true);
                    continue;
                }
                if (is_string($quote)) {
                    $result->append(new Mage_Shipping_Model_Rate_Result_Error()->setCarrier($code)->setErrorMessage($quote));
                    continue;
                }
                if ($quote === null) {
                    $result->append($error);
                    continue;
                }
                $method = new Mage_Shipping_Model_Rate_Result_Method()
                    ->setCarrier($quote[2] ?? null)
                    ->setMethod($code)
                    ->setData('cost', $quote[1]);
                if ($quote[0] !== null) {
                    $method->setPrice($quote[0]);
                }
                $result->append($method);
            }
            return $result;
        }
    };
    $shipping = new class ($carrier, $packages) extends Mage_Shipping_Model_Shipping {
        public function __construct(private readonly Mage_Shipping_Model_Carrier_Abstract $carrier, private readonly array $packages) {}

        #[\Override]
        public function getCarrierByCode(mixed $carrierCode, mixed $storeId = null): Mage_Shipping_Model_Carrier_Abstract
        {
            return $this->carrier;
        }

        #[\Override]
        public function composePackagesForCarrier(mixed $carrier, mixed $request): array
        {
            return $this->packages;
        }
    };

    $shipping->collectCarrierRates('test', new Mage_Shipping_Model_Rate_Request());

    $rates = [];
    foreach ($shipping->getResult()->getAllRates() as $rate) {
        $rates[] = $rate instanceof Mage_Shipping_Model_Rate_Result_Error
            ? $rate->getErrorMessage()
            : [$rate->getMethod(), $rate->getPrice(), $rate->getCost()];
    }
    expect($carrier->weights->getArrayCopy())->toBe(array_map(floatval(...), array_keys($responses)))
        ->and($rates)->toBe($expected);
})->with([
    'common methods in price order and first-package order on ties' => [
        [70 => 2, 5 => 1],
        [
            70 => ['ground' => [4.0, 3.0], 'free' => [0.0, 1.0], 'express' => [3.0, 2.0], 'freight' => [1.0, 1.0]],
            5 => ['express' => [4.0, 5.0], 'ground' => [2.0, 1.0], 'free' => [0.0, 2.0], 'letter' => [1.0, 1.0]],
        ],
        false,
        [['free', 0.0, 4.0], ['ground', 10.0, 7.0], ['express', 10.0, 9.0]],
    ],
    'identical packages share a single request' => [[70 => 2], [70 => ['ground' => [4.0, 3.0]]], false, [['ground', 8.0, 6.0]]],
    'no common method' => [[70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => ['letter' => [1.0, 1.0]]], false, []],
    'empty package response' => [[70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => []], true, []],
    'carrier declined a package' => [[70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => false], true, []],
    'missing package response' => [[70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => null], true, []],
    'method codes from different carriers do not match' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0, 'one']], 5 => ['ground' => [2.0, 1.0, 'two']]],
        false,
        [],
    ],
    'combined cost is unknown if either package cost is missing' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0], 'express' => [5.0, null]], 5 => ['ground' => [2.0, null], 'express' => [3.0, 2.0]]],
        false,
        [['ground', 6.0, null], ['express', 8.0, null]],
    ],
    'a mixed response is hidden when configured' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'express' => null]],
        false,
        [],
    ],
    'a mixed response shows the common method and the error' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'express' => null]],
        true,
        ['Package unavailable', ['ground', 6.0, 4.0]],
    ],
    'a mixed response is hidden when it arrives first' => [
        [5 => 1, 70 => 1],
        [5 => ['ground' => [2.0, 1.0], 'express' => null], 70 => ['ground' => [4.0, 3.0]]],
        false,
        [],
    ],
    'a mixed response shows the common method and the error when it arrives first' => [
        [5 => 1, 70 => 1],
        [5 => ['ground' => [2.0, 1.0], 'express' => null], 70 => ['ground' => [4.0, 3.0]]],
        true,
        ['Package unavailable', ['ground', 6.0, 4.0]],
    ],
    'mixed response for identical packages is hidden when configured' => [
        [70 => 2], [70 => ['ground' => [4.0, 0.0], 'express' => null]], false, [],
    ],
    'mixed response for identical packages shows methods and errors' => [
        [70 => 2], [70 => ['ground' => [4.0, 0.0], 'express' => null]], true, ['Package unavailable', ['ground', 8.0, 0.0]],
    ],
    'an error flag without a message hides mixed rates when configured' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'error' => true]], false, [],
    ],
    'an error flag without a message does not create a diagnostic' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [2.0, 1.0], 'error' => true]], true, [['ground', 6.0, 4.0]],
    ],
    'errors deduplicate by carrier and message' => [
        [70 => 1, 20 => 1, 5 => 1],
        [70 => ['one' => 'Limit applies', 'two' => 'Limit applies'], 20 => ['one' => 'Limit applies'], 5 => ['one' => 'Address unavailable']],
        true,
        ['Limit applies', 'Limit applies', 'Address unavailable'],
    ],
    'a later silent refusal discards the mixed quote' => [
        [70 => 1, 5 => 1], [70 => ['ground' => [4.0, 3.0], 'express' => null], 5 => false], true, [],
    ],
    'a first silent refusal stops the package requests' => [[5 => 1, 70 => 1], [5 => false], true, []],
    'maintains the package cost already scaled by a result override' => [
        [70 => 2], [70 => ['ground' => [4.0, 3.5]]], false, [['ground', 8.0, 7.0]], true,
    ],
    'maintains free and unknown costs alongside custom totals' => [
        [70 => 2],
        [70 => ['free' => [0.0, 0.0], 'ground' => [4.0, '3.5'], 'express' => [5.0, ''], 'priority' => [6.0, null]]],
        false,
        [['free', 0.0, 0.0], ['ground', 8.0, 7.0], ['express', 10.0, null], ['priority', 12.0, null]],
        true,
    ],
    'scales numeric strings and treats non-numeric costs as unknown' => [
        [70 => 2, 5 => 1],
        [70 => ['ground' => [4.0, '3.5'], 'express' => [5.0, '']], 5 => ['ground' => [2.0, '1.5'], 'express' => [3.0, 2.0]]],
        false,
        [['ground', 10.0, 8.5], ['express', 13.0, null]],
    ],
    'shows an existing error when package methods do not overlap' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0], 'error' => null], 5 => ['express' => [2.0, 1.0]]],
        true,
        ['Package unavailable'],
    ],
    'shows the same error when the mixed quote arrives last' => [
        [5 => 1, 70 => 1],
        [5 => ['express' => [2.0, 1.0]], 70 => ['ground' => [4.0, 3.0], 'error' => null]],
        true,
        ['Package unavailable'],
    ],
    'hides the error for disjoint methods when configured' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0], 'error' => null], 5 => ['express' => [2.0, 1.0]]],
        false,
        [],
    ],
    'missing final package price leaves only the explicitly free method' => [
        [70 => 1, 5 => 2],
        [70 => ['ground' => [4.0, 3.0], 'free' => [0.0, 0.0]], 5 => ['ground' => [null, 1.0], 'free' => [0.0, 0.0]]],
        true,
        [['free', 0.0, 0.0]],
    ],
    'missing first package price leaves only the explicitly free method' => [
        [5 => 2, 70 => 1],
        [5 => ['ground' => [null, 1.0], 'free' => [0.0, 0.0]], 70 => ['ground' => [4.0, 3.0], 'free' => [0.0, 0.0]]],
        true,
        [['free', 0.0, 0.0]],
    ],
    'only displays the error when the quote has no priced method' => [
        [70 => 1, 5 => 1],
        [70 => ['ground' => [4.0, 3.0]], 5 => ['ground' => [null, 1.0], 'error' => null]],
        true,
        ['Package unavailable'],
    ],
    'single package keeps the original method with a missing price' => [
        [70 => 1],
        [70 => ['ground' => [null, 3.0]]],
        false,
        [['ground', null, 3.0]],
    ],
    'single package preserves the carrier result when errors are shown' => [
        [70 => 1],
        [70 => ['ground' => [4.0, 3.0], 'express' => null]],
        true,
        ['Package unavailable', ['ground', 4.0, 3.0]],
    ],
    'single package keeps the configured handling of mixed results' => [
        [70 => 1],
        [70 => ['ground' => [4.0, 3.0], 'express' => null]],
        false,
        [],
    ],
    'package error displayed' => [[70 => 1, 5 => 2], [70 => ['ground' => [4.0, 3.0]], 5 => 'error result'], true, ['Package unavailable']],
    'package error hidden' => [[70 => 1, 5 => 2], [70 => ['ground' => [4.0, 3.0]], 5 => 'error result'], false, []],
    'direct package error displayed' => [[70 => 2], [70 => 'error'], true, ['Package unavailable']],
    'direct package error hidden' => [[70 => 2], [70 => 'error'], false, []],
]);

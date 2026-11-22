<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

use Tests\Helpers\PackageCarrierStub;
use Tests\Helpers\ShippingStub;

uses(Tests\MahoBackendTestCase::class);

function decimalPackageRequest(
    float $weight,
    float $qty,
    bool $divisible,
    ?float $increment,
): Mage_Shipping_Model_Rate_Request {
    return new Mage_Shipping_Model_Rate_Request()
        ->setAllItems([decimalPackageItem($weight, $qty, $divisible, $increment)])
        ->setPackageWeight($weight * $qty);
}

function decimalPackageItem(float $weight, float $qty, bool $divisible, ?float $increment): Mage_Sales_Model_Quote_Item
{
    $stock = new Mage_CatalogInventory_Model_Stock_Item([
        'is_qty_decimal' => true,
        'is_decimal_divided' => $divisible,
        'use_config_enable_qty_increments' => false,
        'enable_qty_increments' => $increment !== null,
        'use_config_qty_increments' => false,
        'qty_increments' => $increment,
    ]);
    $product = new Mage_Catalog_Model_Product()->setStockItem($stock);
    return new Mage_Sales_Model_Quote_Item([
        'product' => $product,
        'product_type' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        'is_qty_decimal' => true,
        'qty' => $qty,
        'weight' => $weight,
    ]);
}

$decimalPackageCases = [
    'fractional quantity below one' => [8.0, 0.5, true, null, [4.0]],
    'fractional quantity above one' => [8.0, 1.5, true, null, [12.0]],
    'weight at the package limit' => [40.0, 1.75, true, null, [70.0]],
    'weight above the package limit' => [50.0, 1.5, true, null, [70.0, 5.0]],
    'weight just below two package limits' => [93.332, 1.5, true, null, [70.0, 69.998]],
    'two packages of the same weight' => [80.0, 1.75, true, null, [70.0, 70.0]],
    'weight just above two package limits' => [93.334, 1.5, true, null, [70.0, 70.0, 0.001]],
    'quantity increments' => [8.0, 1.5, true, 0.5, [12.0]],
    'indivisible quantity' => [8.0, 0.5, false, null, [4.0]],
];

it('quotes the actual package weights for decimal quantities', function (
    float $weight,
    float $qty,
    bool $divisible,
    ?float $increment,
    array $expected,
    float $maxWeight = 70,
): void {
    $request = decimalPackageRequest($weight, $qty, $divisible, $increment);
    $carrier = new PackageCarrierStub(
        fn(float $packageWeight) => new Mage_Shipping_Model_Rate_Result()->append(
            new Mage_Shipping_Model_Rate_Result_Method(['method' => 'test', 'price' => $packageWeight]),
        ),
        $maxWeight,
    );
    $shipping = new ShippingStub(['test' => $carrier]);
    $shipping->collectCarrierRates('test', $request);

    $packages = $shipping->composePackagesForCarrier($carrier, $request);
    $uniqueWeights = array_values(array_unique($expected, SORT_NUMERIC));
    $roundWeight = static fn(float|int|string $weight): float => round((float) $weight, 3);
    expect(array_map($roundWeight, array_keys($packages)))->toBe($uniqueWeights)
        ->and(array_map($roundWeight, $carrier->getRequestedWeights()))->toBe($uniqueWeights)
        ->and($shipping->getResult()->getCheapestRate()?->getPrice())->toBe(round(array_sum($expected), 2));
})->with([
    ...$decimalPackageCases,
    'no package weight limit' => [8.0, 1.5, true, null, [12.0], 0],
    'decimal package limit with no remainder' => [140.8, 1.75, true, null, array_fill(0, 11, 22.4), 22.4],
]);

it('quotes real package weights and charges once for each package at the carrier limit', function (
    array $lines,
    float $limit,
    array $expectedWeights,
): void {
    $items = [];
    $orderWeight = 0.0;
    foreach ($lines as [$weight, $qty]) {
        $items[] = decimalPackageItem($weight, $qty, true, null);
        $orderWeight += $weight * $qty;
    }
    $request = new Mage_Shipping_Model_Rate_Request()->setAllItems($items)->setPackageWeight($orderWeight);
    $carrier = new PackageCarrierStub(
        fn() => new Mage_Shipping_Model_Rate_Result()->append(
            new Mage_Shipping_Model_Rate_Result_Method(['method' => 'test', 'price' => 10.0]),
        ),
        $limit,
    );
    $shipping = new ShippingStub(['test' => $carrier]);
    $shipping->collectCarrierRates('test', $request);

    $weights = $carrier->getRequestedWeights();
    expect(array_map(static fn(float $weight): float => round($weight, 8), $weights))->toBe($expectedWeights)
        ->and(max($weights))->toBeLessThanOrEqual($limit)
        ->and(array_sum($weights))->toEqualWithDelta($orderWeight, 0.000000001)
        ->and($shipping->getResult()->getCheapestRate()?->getPrice())->toBe(10.0 * count($expectedWeights));
})->with([
    'the sum of two decimal lines reaches the limit' => [[[0.2, 0.5], [0.4, 0.5]], 0.3, [0.3]],
    'the sum of two decimal lines exceeds the limit' => [[[0.2, 0.5], [0.4002, 0.5]], 0.3, [0.2001, 0.1]],
    'a remainder stays below the configured limit' => [[[0.3, 1.0005]], 0.15009, [0.15009, 0.15006]],
    'a real remainder smaller than 0.0001 is quoted' => [[[0.1, 1.0004]], 0.1, [0.1, 0.00004]],
    'one hundred hundredths fill one package' => [array_fill(0, 100, [0.01, 1.0]), 1.0, [1.0]],
    'one hundred tenths fill one package' => [array_fill(0, 100, [0.1, 1.0]), 10.0, [10.0]],
    'a small real excess after one hundred lines still needs another package' => [
        [...array_fill(0, 99, [0.01, 1.0]), [0.01, 1.0001]],
        1.0,
        [0.990001, 0.01],
    ],
]);

it('writes the actual package weights into the DHL quote XML', function (
    float $weight,
    float $qty,
    bool $divisible,
    ?float $increment,
    array $expected,
    string $storeUnit = 'K',
    string $originCountry = 'IT',
    string $expectedUnit = 'KG',
    array $extraWeights = [],
): void {
    $request = decimalPackageRequest($weight, $qty, $divisible, $increment);
    $items = $request->getAllItems();
    foreach ($extraWeights as $extraWeight) {
        $items[] = new Mage_Sales_Model_Quote_Item([
            'product' => new Mage_Catalog_Model_Product()->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE),
            'product_type' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE, 'qty' => 1, 'weight' => $extraWeight,
        ]);
    }
    $request->setAllItems($items)->setPackageWeight($weight * $qty + array_sum($extraWeights))
        ->setOrigCountryId($originCountry)
        ->setOrigCity('Rome')
        ->setOrigPostcode('00100')
        ->setDestCountryId('IT')
        ->setDestPostcode('20121')
        ->setDestCity('Milan')
        ->setDestStreet('1 Example Street')
        ->setPackageValue(10.0);
    $carrier = new class ($storeUnit) extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public ?string $quoteRequest = null;

        public function __construct(private readonly string $storeUnit)
        {
            parent::__construct();
        }

        #[\Override]
        public function getConfigFlag(mixed $field): bool
        {
            return $field === 'active';
        }

        #[\Override]
        public function getConfigData($field): ?string
        {
            return match ($field) {
                'unit_of_measure' => $this->storeUnit,
                'divide_order_weight' => '1',
                default => null,
            };
        }

        #[\Override]
        protected function _getQuotesFromServer(mixed $request): string
        {
            $this->quoteRequest = $request;
            return '<r/>';
        }
    };

    $carrier->collectRates($request);

    expect($carrier->quoteRequest)->not->toBeNull();
    $details = new SimpleXMLElement($carrier->quoteRequest)->GetQuote->BkgDetails;
    $weights = [];
    foreach ($details->Pieces->Piece as $piece) {
        $weights[] = (float) $piece->Weight;
    }
    expect((string) $details->WeightUnit)->toBe($expectedUnit)
        ->and($weights)->toBe($expected);
})->with([
    ...$decimalPackageCases,
    'US origin converts 140 kilograms across two pieces' => [80.0, 1.75, true, null, [154.324, 154.323], 'K', 'US', 'LB'],
    'pounds converted to kilograms once' => [5.0, 0.5, true, null, [1.134], 'L'],
    'pounds converted to kilograms across the package limit' => [100.0, 2.0, true, null, [70.0, 20.718], 'L'],
    'combined lines at the limit' => [139.996, 0.5, true, null, [70.0], 'K', 'IT', 'KG', [0.001, 0.001]],
    'combined lines exceeding the limit' => [139.998, 0.5, true, null, [70.0, 0.001], 'K', 'IT', 'KG', [0.001, 0.001]],
    'one hundred lines fill one package' => [0.7, 1.0, true, null, [70.0], 'K', 'IT', 'KG', array_fill(0, 99, 0.7)],
    'one thousand lines fill one package' => [0.07, 1.0, true, null, [70.0], 'K', 'IT', 'KG', array_fill(0, 999, 0.07)],
]);

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function decimalPackageRequest(
    float $weight,
    float $qty,
    bool $divisible,
    ?float $increment,
): Mage_Shipping_Model_Rate_Request {
    $stock = new Mage_CatalogInventory_Model_Stock_Item([
        'is_qty_decimal' => true,
        'is_decimal_divided' => $divisible,
        'use_config_enable_qty_increments' => false,
        'enable_qty_increments' => $increment !== null,
        'use_config_qty_increments' => false,
        'qty_increments' => $increment,
    ]);
    $product = new Mage_Catalog_Model_Product()->setStockItem($stock);
    $item = new Mage_Sales_Model_Quote_Item([
        'product' => $product,
        'product_type' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        'is_qty_decimal' => true,
        'qty' => $qty,
        'weight' => $weight,
    ]);

    return new Mage_Shipping_Model_Rate_Request()
        ->setAllItems([$item])
        ->setPackageWeight($weight * $qty);
}

$decimalPackageCases = [
    'fractional quantity below one' => [8.0, 0.5, true, null, [4.0]],
    'fractional quantity above one' => [8.0, 1.5, true, null, [12.0]],
    'weight at the package limit' => [40.0, 1.75, true, null, [70.0]],
    'weight above the package limit' => [50.0, 1.5, true, null, [70.0, 5.0]],
    'quantity increments' => [8.0, 1.5, true, 0.5, [12.0]],
    'indivisible quantity' => [8.0, 0.5, false, null, [4.0]],
];

it('quotes the actual package weights for decimal quantities', function (
    float $weight,
    float $qty,
    bool $divisible,
    ?float $increment,
    array $expected,
): void {
    $request = decimalPackageRequest($weight, $qty, $divisible, $increment);
    $carrier = new class extends Mage_Shipping_Model_Carrier_Abstract {
        /** @var ArrayObject<int, float> */
        public ArrayObject $weights;

        public function __construct()
        {
            parent::__construct();
            $this->weights = new ArrayObject();
        }

        #[\Override]
        public function getConfigData($field): int
        {
            return match ($field) {
                'shipment_requesttype' => 1,
                'max_package_weight' => 70,
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

        public function collectRates(Mage_Shipping_Model_Rate_Request $request): Mage_Shipping_Model_Rate_Result
        {
            $weight = (float) $request->getPackageWeight();
            $this->weights->append($weight);

            return new Mage_Shipping_Model_Rate_Result()->append(
                new Mage_Shipping_Model_Rate_Result_Method(['method' => 'test', 'price' => $weight]),
            );
        }
    };

    $shipping = new class ($carrier) extends Mage_Shipping_Model_Shipping {
        public function __construct(private readonly Mage_Shipping_Model_Carrier_Abstract $carrier) {}

        #[\Override]
        public function getCarrierByCode($carrierCode, $storeId = null): Mage_Shipping_Model_Carrier_Abstract
        {
            return $this->carrier;
        }
    };
    $shipping->collectCarrierRates('test', $request);

    $packages = $shipping->composePackagesForCarrier($carrier, $request);
    expect(array_map(floatval(...), array_keys($packages)))->toBe($expected)
        ->and($carrier->weights->getArrayCopy())->toBe($expected)
        ->and($shipping->getResult()->getCheapestRate()?->getPrice())->toBe(array_sum($expected));
})->with($decimalPackageCases);

it('writes the actual package weights into the DHL quote XML', function (
    float $weight,
    float $qty,
    bool $divisible,
    ?float $increment,
    array $expected,
    string $storeUnit = 'K',
): void {
    $request = decimalPackageRequest($weight, $qty, $divisible, $increment);
    $request->setOrigCountryId('IT')
        ->setOrigCity('Rome')
        ->setDestCountryId('IT')
        ->setDestPostcode('20121')
        ->setDestCity('Milan')
        ->setDestStreet('1 Example Street')
        ->setPackageValue(10.0)
        ->setData('orig_postal', '00100');
    $carrier = new class ($storeUnit) extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public function __construct(private readonly string $storeUnit)
        {
            parent::__construct();
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

        /** @return list<float> */
        public function packageWeights(Mage_Shipping_Model_Rate_Request $request): array
        {
            $this->setRequest($request);
            $quote = $this->_buildQuotesRequestXml();

            $weights = [];
            foreach ($quote->GetQuote->BkgDetails->Pieces->Piece as $piece) {
                $weights[] = (float) $piece->Weight;
            }
            return $weights;
        }
    };

    expect($carrier->packageWeights($request))->toBe($expected);
})->with([
    ...$decimalPackageCases,
    'pounds converted to kilograms once' => [5.0, 0.5, true, null, [1.134], 'L'],
]);

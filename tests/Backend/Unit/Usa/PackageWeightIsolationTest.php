<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('keeps the full order weight for a carrier after a carrier that quotes per package', function (bool $declines): void {
    $stock = new Mage_CatalogInventory_Model_Stock_Item([
        'is_qty_decimal' => true,
        'is_decimal_divided' => true,
        'use_config_enable_qty_increments' => false,
        'enable_qty_increments' => false,
    ]);
    $product = new Mage_Catalog_Model_Product()->setStockItem($stock);
    $item = new Mage_Sales_Model_Quote_Item([
        'product' => $product,
        'product_type' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
        'is_qty_decimal' => true,
        'qty' => 1.5,
        'weight' => 50.0,
    ]);
    $request = new Mage_Shipping_Model_Rate_Request()
        ->setStoreId(1)
        ->setAllItems([$item])
        ->setPackageWeight(75.0)
        ->setPackageValue(10.0)
        ->setOrigCountryId('IT')
        ->setOrigCity('Rome')
        ->setOrigPostal('00100')
        ->setDestCountryId('IT')
        ->setDestCity('Milan')
        ->setDestPostcode('20121')
        ->setDestStreet('1 Example Street')
        ->setLimitCarrier(['fedex', 'dhlint']);
    $splitCarrier = new class ($declines) extends Mage_Shipping_Model_Carrier_Abstract {
        /** @var ArrayObject<int, float> */
        public ArrayObject $weights;

        public function __construct(private readonly bool $declines)
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

        #[\Override]
        public function collectRates(Mage_Shipping_Model_Rate_Request $request): Mage_Shipping_Model_Rate_Result|false
        {
            $this->weights->append((float) $request->getPackageWeight());
            if ($this->declines) {
                return false;
            }

            return new Mage_Shipping_Model_Rate_Result()->append(
                new Mage_Shipping_Model_Rate_Result_Method(['method' => 'test', 'price' => 5.0]),
            );
        }
    };
    $dhl = new class extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public ?float $quotedWeight = null;

        #[\Override]
        public function getConfigFlag($field): bool
        {
            return $field === 'active';
        }

        #[\Override]
        public function getConfigData($field): ?string
        {
            return $field === 'unit_of_measure' ? 'K' : null;
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
        protected function _getQuotes(): Mage_Shipping_Model_Rate_Result
        {
            $quote = $this->_buildQuotesRequestXml();
            $this->quotedWeight = (float) $quote->GetQuote->BkgDetails->Pieces->Piece->Weight;
            return new Mage_Shipping_Model_Rate_Result();
        }
    };
    $shipping = new class ($splitCarrier, $dhl) extends Mage_Shipping_Model_Shipping {
        public function __construct(
            private readonly Mage_Shipping_Model_Carrier_Abstract $splitCarrier,
            private readonly Mage_Usa_Model_Shipping_Carrier_Dhl_International $dhl,
        ) {}

        #[\Override]
        public function getCarrierByCode($carrierCode, $storeId = null): Mage_Shipping_Model_Carrier_Abstract
        {
            return match ($carrierCode) {
                'fedex' => $this->splitCarrier,
                'dhlint' => $this->dhl,
            };
        }
    };

    $shipping->collectRates($request);

    expect($splitCarrier->weights->getArrayCopy())->toBe($declines ? [70.0] : [70.0, 5.0])
        ->and($dhl->quotedWeight)->toBe(75.0)
        ->and($request->getPackageWeight())->toBe(75.0);
})->with(['the split carrier returned rates' => false, 'the split carrier declined the first package' => true]);

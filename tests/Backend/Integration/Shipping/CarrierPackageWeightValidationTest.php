<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('quotes decimal quantities at the configured package weight limit', function (
    float $weight,
    float $qty,
    ?float $increment,
    float $limit,
    bool $packItems,
    ?float $expectedWeight,
    ?float $expectedPrice,
): void {
    $product = Mage::getModel('catalog/product')->setStoreId(0)
        ->setTypeId(Mage_Catalog_Model_Product_Type::TYPE_SIMPLE)
        ->setAttributeSetId(Mage::getModel('catalog/product')->getDefaultAttributeSetId())
        ->setSku(uniqid('decimal-weight-'))->setName('Decimal weight product')->setPrice(10.0)->setWeight($weight)
        ->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)
        ->setTaxClassId(0)->setWebsiteIds([1])
        ->setStockData([
            'use_config_manage_stock' => false,
            'manage_stock' => true,
            'is_in_stock' => true,
            'qty' => 10,
            'is_qty_decimal' => true,
            'is_decimal_divided' => $increment !== null,
            'use_config_enable_qty_increments' => false,
            'enable_qty_increments' => $increment !== null,
            'use_config_qty_increments' => false,
            'qty_increments' => $increment ?? 0,
        ]);
    $product->save();

    try {
        $quote = Mage::getModel('sales/quote')->setStoreId(1);
        $loadedProduct = Mage::getModel('catalog/product')->setStoreId(1)->load($product->getId());
        $item = $quote->addProduct($loadedProduct, new Maho\DataObject(['qty' => $qty]));
        expect($item)->toBeInstanceOf(Mage_Sales_Model_Quote_Item::class)
            ->and($item->getProduct()->getId())->toBe($product->getId())
            ->and($item->getProduct()->getStockItem()->getId())->toBeGreaterThan(0)
            ->and((bool) $item->getHasError())->toBeFalse()
            ->and((float) $item->getQty())->toBe($qty);

        $carrier = new class ($limit, $packItems) extends Mage_Usa_Model_Shipping_Carrier_Fedex {
            public readonly ArrayObject $quotedWeights;

            public function __construct(private readonly float $limit, private readonly bool $packItems)
            {
                parent::__construct();
                $this->quotedWeights = new ArrayObject();
            }

            #[\Override]
            public function getConfigFlag(mixed $field): bool
            {
                return $field === 'active';
            }

            #[\Override]
            public function getConfigData(mixed $field): string|float|bool|null
            {
                return match ($field) {
                    'client_id', 'client_secret' => 'fixture',
                    'allowed_methods' => 'FEDEX_GROUND',
                    'max_package_weight' => $this->limit,
                    'shipment_requesttype' => $this->packItems,
                    'specificerrmsg' => 'Package exceeds the weight limit.',
                    'showmethod' => true,
                    default => null,
                };
            }

            #[\Override]
            protected function _doRatesRequest(mixed $purpose): array
            {
                $this->quotedWeights->append((float) $this->_rawRequest->getWeight());
                return ['output' => ['rateReplyDetails' => [[
                    'serviceType' => 'FEDEX_GROUND',
                    'ratedShipmentDetails' => [['rateType' => 'ACCOUNT', 'totalNetCharge' => 10.0]],
                ]]]];
            }
        };

        $shipping = new class ($carrier) extends Mage_Shipping_Model_Shipping {
            public function __construct(private readonly Mage_Usa_Model_Shipping_Carrier_Fedex $carrier) {}

            #[\Override]
            public function getCarrierByCode(mixed $carrierCode, mixed $storeId = null): Mage_Usa_Model_Shipping_Carrier_Fedex
            {
                return $this->carrier;
            }
        };
        $request = new Mage_Shipping_Model_Rate_Request()
            ->setAllItems($quote->getAllItems())
            ->setPackageWeight($item->getWeight() * $item->getQty())
            ->setStoreId(1)->setOrigCountry('US')->setOrigPostcode('10001')
            ->setDestCountryId('US')->setDestPostcode('90210');

        $shipping->collectCarrierRates('fedex', $request);
        $rates = $shipping->getResult()->getAllRates();
        expect($rates)->toHaveCount(1);
        if ($expectedPrice === null) {
            expect($rates[0])->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Error::class)
                ->and($rates[0]->getErrorMessage())->toBe('Package exceeds the weight limit.')
                ->and($carrier->quotedWeights)->toHaveCount(0);
            return;
        }
        expect($rates[0])->toBeInstanceOf(Mage_Shipping_Model_Rate_Result_Method::class)
            ->and($rates[0]->getMethod())->toBe('FEDEX_GROUND')
            ->and($rates[0]->getPrice())->toBe($expectedPrice)
            ->and($carrier->quotedWeights)->toHaveCount(1)
            ->and($carrier->quotedWeights[0])->toEqualWithDelta($expectedWeight, 0.000000001);
    } finally {
        $product->delete();
    }
})->with([
    'one indivisible quantity' => [0.1, 3.0, null, 0.3, true, 0.3, 10.0],
    'one quantity increment' => [0.1, 3.0, 3.0, 0.3, true, 0.3, 10.0],
    'two quantity increments' => [0.1, 6.0, 3.0, 0.3, true, 0.3, 20.0],
    'one indivisible quantity with carrier packing' => [0.1, 3.0, null, 0.3, false, 0.3, 10.0],
    'two increments with carrier packing' => [0.1, 6.0, 3.0, 0.3, false, 0.3, 20.0],
    'a real excess with carrier packing' => [0.2001, 3.0, 1.0, 0.3, false, 0.2001, 30.0],
    'no configured package weight limit' => [0.1, 3.0, null, 0.0, false, 0.3, 10.0],
    'indivisible quantity above the limit' => [0.1001, 3.0, null, 0.3, true, null, null],
    'quantity increment above the limit' => [0.1001, 3.0, 3.0, 0.3, true, null, null],
]);

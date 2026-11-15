<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class UpsRegionPayloadCaptured extends RuntimeException {}

final class UpsShipmentRegionProbe extends Mage_Usa_Model_Shipping_Carrier_Ups
{
    public array $payload = [];
    public ?\Maho\DataObject $shipmentRequest = null;

    #[\Override]
    protected function _formShipmentRestRequest(\Maho\DataObject $request): string
    {
        $this->shipmentRequest = clone $request;
        $this->payload = json_decode(parent::_formShipmentRestRequest($request), true, 512, JSON_THROW_ON_ERROR);

        // Stop the native shipment path before UPS authentication or transport.
        throw new UpsRegionPayloadCaptured();
    }
}

it('preserves the recipient region in the UPS shipment payload', function (
    string $country,
    ?string $region,
    ?string $directoryCode,
    ?string $expectedRegion,
) {
    $store = Mage::app()->getStore();
    $config = [
        'general/store_information/name' => 'Shipment fixture',
        'general/store_information/phone' => '2025550100',
        'shipping/origin/street_line1' => '1 Example Street',
        'shipping/origin/street_line2' => '',
        'shipping/origin/city' => 'Los Angeles',
        'shipping/origin/region_id' => 'CA',
        'shipping/origin/postcode' => '90001',
        'shipping/origin/country_id' => 'US',
        'carriers/ups/shipper_number' => 'TEST00',
    ];
    $originalConfig = [];
    foreach ($config as $path => $value) {
        $originalConfig[$path] = $store->getConfig($path);
        $store->setConfig($path, $value);
    }
    $session = Mage::getSingleton('admin/session');
    $originalUser = $session->getUser();
    $session->setUser(Mage::getModel('admin/user')->addData([
        'firstname' => 'Shipping',
        'lastname' => 'Fixture',
        'email' => 'shipping@example.test',
    ]));

    try {
        $address = Mage::getModel('sales/order_address')->addData([
            'firstname' => 'Recipient',
            'lastname' => 'Fixture',
            'street' => '2 Example Street',
            'city' => $country === 'GB' ? 'Oxford' : 'Los Angeles',
            'postcode' => $country === 'GB' ? 'OX1 1AA' : '90001',
            'country_id' => $country,
            'region' => $region,
            'telephone' => '2025550101',
            'email' => 'recipient@example.test',
        ]);
        if ($directoryCode !== null) {
            $directoryRegion = Mage::getModel('directory/region')->loadByCode($directoryCode, $country);
            expect($directoryRegion->getId())->not->toBeNull();
            $address->setRegionId((int) $directoryRegion->getId());
        } else {
            expect($address->getRegionId())->toBeNull();
        }
        expect($address->validate())->toBeTrue();

        $carrier = new UpsShipmentRegionProbe();
        $order = Mage::getModel('sales/order')->addData([
            'store_id' => $store->getId(),
            'shipping_method' => 'ups_03',
            'shipping_carrier' => $carrier,
            'increment_id' => 'SYNTHETIC-UPS-REGION',
            'weight' => 1,
        ]);
        $order->setShippingAddress($address);
        $shipment = Mage::getModel('sales/order_shipment')->setOrder($order)->setPackages([
            1 => [
                'params' => ['container' => '02', 'weight' => 1, 'weight_units' => 'LB'],
                'items' => [['name' => 'Synthetic item', 'qty' => 1]],
            ],
        ]);

        expect(fn() => Mage::getModel('shipping/shipping')->requestToShipment($shipment))
            ->toThrow(UpsRegionPayloadCaptured::class);

        // Both fields are set by Shipping, but only the first retains a free-text region.
        expect($carrier->shipmentRequest->getRecipientAddressStateOrProvinceCode())->toBe($expectedRegion)
            ->and($carrier->shipmentRequest->getRecipientAddressRegionCode())->toBe($directoryCode);
        $shipTo = $carrier->payload['ShipmentRequest']['Shipment']['ShipTo']['Address'];
        expect($shipTo['CountryCode'])->toBe($country);
        if ($expectedRegion === null) {
            expect($shipTo)->not->toHaveKey('StateProvinceCode');
        } else {
            expect($shipTo)->toHaveKey('StateProvinceCode', $expectedRegion);
        }
    } finally {
        $session->setUser($originalUser);
        foreach ($originalConfig as $path => $value) {
            $store->setConfig($path, $value);
        }
    }
})->with([
    'free-text region without a directory ID' => ['GB', 'Oxfordshire', null, 'Oxfordshire'],
    'directory region' => ['US', 'California', 'CA', 'CA'],
    'absent region' => ['GB', null, null, null],
]);

it('cuts the UPS shipment description at 35 bytes without splitting a character', function (string $name, string $expected) {
    $method = new ReflectionMethod(Mage_Usa_Model_Shipping_Carrier_Ups::class, 'generateShipmentDescription');
    $description = $method->invoke(new Mage_Usa_Model_Shipping_Carrier_Ups(), [['name' => $name]]);

    expect($description)->toBe($expected)
        ->and(json_encode($description))->not->toBeFalse();
})->with([
    'ASCII over the limit' => [str_repeat('A', 36), str_repeat('A', 35)],
    'accent crossing the limit' => [str_repeat('A', 34) . 'éB', str_repeat('A', 34)],
    'accent fitting the limit' => [str_repeat('A', 33) . 'éB', str_repeat('A', 33) . 'é'],
]);

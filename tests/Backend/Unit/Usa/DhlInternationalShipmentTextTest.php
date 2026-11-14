<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function dhlShipmentRequestXml(array $data): string
{
    $carrier = new class extends Mage_Usa_Model_Shipping_Carrier_Dhl_International {
        public array $debugData = [];

        #[\Override]
        public function getConfigData(mixed $field): ?string
        {
            return match ($field) {
                'gateway_url' => 'unsupported-scheme://localhost',
                default => null,
            };
        }

        #[\Override]
        protected function _debug(mixed $debugData): void
        {
            $this->debugData = $debugData;
        }

        #[\Override]
        protected function getCountryParams($countryCode): \Maho\DataObject
        {
            return new \Maho\DataObject(['region' => 'EU', 'name' => 'Italy', 'measure_unit' => 'CM', 'weight_unit' => 'KG']);
        }

        public function requestShipment(\Maho\DataObject $request): void
        {
            $this->_request = $request;
            $this->_rawRequest = new \Maho\DataObject(['orig_country_id' => 'IT']);
            try {
                $this->_doRequest();
            } catch (Mage_Core_Exception) {
                // The gateway gives no response. The request is already in the debug data.
            }
        }
    };

    $carrier->requestShipment(new \Maho\DataObject($data + [
        'shipper_address_country_code' => 'IT',
        'recipient_address_country_code' => 'IT',
        'shipping_method' => 'N',
        'package_weight' => 1,
        'packages' => [[
            'params' => ['container' => 'N', 'weight' => 1, 'width' => 0, 'length' => 0, 'height' => 0],
            'items' => [['name' => 'Panettone']],
        ]],
    ]));

    return $carrier->debugData['request'];
}

it('sends accented names to DHL as UTF-8, without a second encoding', function (): void {
    $request = dhlShipmentRequestXml(['recipient_address_city' => 'Forlì']);

    expect($request)->toContain('<City>Forlì</City>');
});

it('cuts names in the DHL shipment request at a number of characters, not bytes', function (): void {
    $request = dhlShipmentRequestXml([
        'recipient_contact_company_name' => 'Gran Torrefazione Brambilla Società Agricola',
        'recipient_contact_person_name' => 'Maria Antonietta Brambilla Niccolò',
        'shipper_contact_person_name' => 'Giuseppe Ferdinando Brambilla Lucà',
        'packages' => [[
            'params' => ['container' => 'N', 'weight' => 1, 'width' => 0, 'length' => 0, 'height' => 0],
            'items' => [['name' => 'Gran Panettone glassé'], ['name' => 'Torrone'], ['name' => 'Crème brûlée']],
        ]],
    ]);

    expect(mb_check_encoding($request, 'UTF-8'))->toBeTrue();
    expect($request)->toContain('<CompanyName>Gran Torrefazione Brambilla Società</CompanyName>');
    expect($request)->toContain('<PersonName>Maria Antonietta Brambilla Niccolò</PersonName>');
    expect($request)->toContain('<PersonName>Giuseppe Ferdinando Brambilla Lucà</PersonName>');
    expect($request)->toContain('<PieceContents>Gran Panettone glassé,Torrone,Crèm</PieceContents>');
});

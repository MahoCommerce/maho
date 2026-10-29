<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function carrierTrackingUrlTrack(string $carrierCode, string $number): Mage_Sales_Model_Order_Shipment_Track
{
    return Mage::getModel('sales/order_shipment_track')
        ->setCarrierCode($carrierCode)
        ->setNumber($number)
        ->setTitle('Test carrier');
}

describe('carrier tracking url', function () {
    test('composes the page of a carrier from the configured template', function () {
        $carrier = Mage::getSingleton('shipping/config')->getCarrierInstance('ups');

        expect($carrier)->toBeInstanceOf(Mage_Usa_Model_Shipping_Carrier_Ups::class);
        expect($carrier->getTrackingUrl('1Z999AA10123456784'))
            ->toBe('https://www.ups.com/track?tracknum=1Z999AA10123456784');
    });

    test('url-encodes the number before replacing the placeholder', function () {
        $carrier = Mage::getSingleton('shipping/config')->getCarrierInstance('fedex');

        expect($carrier->getTrackingUrl('12 34/56'))
            ->toBe('https://www.fedex.com/fedextrack/?trknbr=12%2034%2F56');
    });

    test('returns null when the carrier has no tracking page configured', function () {
        $carrier = Mage::getSingleton('shipping/config')->getCarrierInstance('dhlint');

        expect($carrier->getTrackingUrl('1234567890'))->toBeNull();
    });

    test('returns null on carriers without a tracking page', function () {
        $carrier = Mage::getSingleton('shipping/config')->getCarrierInstance('flatrate');

        expect($carrier->getTrackingUrl('1234567890'))->toBeNull();
    });

    test('composes the url of a shipment track without asking the carrier for the status', function () {
        $track = carrierTrackingUrlTrack('ups', '1Z999AA10123456784');

        expect($track->getTrackingUrl())
            ->toBe('https://www.ups.com/track?tracknum=1Z999AA10123456784');
    });

    test('returns null for a custom track or a missing number', function () {
        expect(carrierTrackingUrlTrack('custom', 'TRACK-1')->getTrackingUrl())->toBeNull();
        expect(carrierTrackingUrlTrack('ups', '')->getTrackingUrl())->toBeNull();
    });
});

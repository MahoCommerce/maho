<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

$payload = '<img src=x onerror=alert(1)>';

afterEach(function (): void {
    Mage::unregister('current_shipping_info');
});

it('escapes the carrier data in the tracking popup', function () use ($payload): void {
    $summaryTrack = new Maho\DataObject([
        'tracking' => '1Z' . $payload,
        'carrier_title' => 'UPS',
        'track_summary' => $payload,
    ]);
    $detailTrack = new Maho\DataObject([
        'tracking' => '2Z',
        'status' => $payload,
        'signedby' => $payload,
        'service' => $payload,
        'weight' => $payload,
        'delivery_location' => $payload,
        'shipped_date' => $payload,
    ]);
    Mage::register('current_shipping_info', new Maho\DataObject(['tracking_info' => [1 => [$summaryTrack, $detailTrack]]]));

    $html = Mage::app()->getLayout()->createBlock('shipping/tracking_popup')
        ->setTemplate('shipping/tracking/popup.phtml')
        ->toHtml();

    expect($html)->toContain('&lt;img src=x')
        ->and($html)->not->toContain('<img src=x');
});

it('escapes the carrier data in the order tracking info', function () use ($payload): void {
    $html = Mage::app()->getLayout()->createBlock('core/template')
        ->setTemplate('sales/order/trackinginfo.phtml')
        ->setTrackingInfo(new Maho\DataObject([
            'tracking' => '1Z' . $payload,
            'carrier_title' => 'UPS',
            'status' => $payload,
            'track_summary' => $payload,
        ]))
        ->toHtml();

    expect($html)->toContain('&lt;img src=x')
        ->and($html)->not->toContain('<img src=x');
});

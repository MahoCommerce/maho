<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Shipping
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Shipping_Model_Tracking_Result::append()', function () {
    test('adds the trackings of another tracking result', function () {
        $first = Mage::getModel('shipping/tracking_result_status')->setTracking('1Z0001');
        $second = Mage::getModel('shipping/tracking_result_status')->setTracking('1Z0002');

        $other = Mage::getModel('shipping/tracking_result');
        $other->append($second);

        $result = Mage::getModel('shipping/tracking_result');
        $result->append($first);
        $result->append($other);

        expect($result->getAllTrackings())->toBe([$first, $second]);
    });
});

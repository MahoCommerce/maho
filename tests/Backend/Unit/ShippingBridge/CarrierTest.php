<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class ShippingBridgeCarrierProbe extends Maho_ShippingBridge_Model_Carrier
{
    public function parse(string $responseBody): ?array
    {
        return $this->_parseResponse($responseBody);
    }
}

it('skips a method whose code, title or price has the wrong type, and keeps the valid ones', function (): void {
    $methods = (new ShippingBridgeCarrierProbe())->parse(Mage::helper('core')->jsonEncode(['methods' => [
        ['code' => 123, 'title' => 'Numeric code', 'price' => 5],
        ['code' => 'array_title', 'title' => ['Express'], 'price' => 5],
        ['code' => 'text_price', 'title' => 'Text price', 'price' => 'free'],
        ['code' => 'standard', 'title' => 'Standard', 'price' => 5.99],
    ]]));

    expect($methods)->toBe([['code' => 'standard', 'title' => 'Standard', 'price' => 5.99]]);
});

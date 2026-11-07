<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ShippingBridge
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('ignores a shipping method with an invalid price and keeps valid methods', function (mixed $price): void {
    $carrier = new Maho_ShippingBridge_Model_Carrier();
    $parse = new ReflectionMethod($carrier, '_parseResponse');
    $methods = [
        ['code' => 'invalid', 'title' => 'Invalid method', 'price' => $price],
        ['code' => 'free', 'title' => 'Free shipping', 'price' => 0],
        ['code' => 'standard', 'title' => 'Standard shipping', 'price' => '5.99'],
    ];

    expect($parse->invoke($carrier, json_encode(['methods' => $methods], JSON_THROW_ON_ERROR)))
        ->toBe([$methods[1], $methods[2]]);
})->with([
    'negative amount' => [-5.0],
    'text' => ['free'],
    'boolean' => [true],
    'array' => [[5.99]],
    'overflowing number' => ['1e309'],
]);

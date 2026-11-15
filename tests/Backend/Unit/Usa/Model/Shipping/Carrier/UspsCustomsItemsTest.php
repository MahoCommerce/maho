<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

final class UspsCustomsItemsProbe extends Mage_Usa_Model_Shipping_Carrier_Usps
{
    public function buildItems(\Maho\DataObject $request): array
    {
        return $this->buildCustomsItems($request);
    }
}

it('keeps customs descriptions valid UTF-8 within the existing byte limit', function (
    string $name,
    string $expected,
): void {
    $item = [
        'name' => $name,
        'qty' => 2,
        'customs_value' => 12.5,
        'weight' => 0.75,
        'country_of_manufacture' => 'FR',
        'hs_code' => '490199',
    ];
    $items = (new UspsCustomsItemsProbe())->buildItems(new \Maho\DataObject(['package_items' => [$item]]));

    expect($items)->toBe([[
        'description' => $expected,
        'quantity' => 2,
        'value' => 12.5,
        'weight' => 0.75,
        'originCountry' => 'FR',
        'tariffNumber' => '490199',
    ]]);
    expect(mb_check_encoding($items[0]['description'], 'UTF-8'))->toBeTrue();
    expect(strlen($items[0]['description']))->toBeLessThanOrEqual(50);
    expect(json_decode(json_encode($items, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR))->toBe($items);
})->with([
    'short ASCII' => ['Book', 'Book'],
    'exact ASCII limit' => [str_repeat('A', 50), str_repeat('A', 50)],
    'long ASCII' => [str_repeat('A', 51), str_repeat('A', 50)],
    'short Unicode' => ['Café 日本語', 'Café 日本語'],
    'accent crossing limit' => [str_repeat('A', 49) . 'éB', str_repeat('A', 49)],
    'accent fitting limit' => [str_repeat('A', 48) . 'éB', str_repeat('A', 48) . 'é'],
    'CJK crossing limit' => [str_repeat('界', 17), str_repeat('界', 16)],
    'emoji crossing limit' => [str_repeat('📦', 13), str_repeat('📦', 12)],
    'emoji fitting limit' => [str_repeat('A', 46) . '📦B', str_repeat('A', 46) . '📦'],
]);

it('preserves the fallback customs item when the package has no items', function (): void {
    $request = new \Maho\DataObject([
        'package_items' => [],
        'package_params' => new \Maho\DataObject(['customs_value' => 25]),
        'package_weight' => 1.5,
    ]);

    expect((new UspsCustomsItemsProbe())->buildItems($request))->toBe([[
        'description' => 'Package Contents',
        'quantity' => 1,
        'value' => 25.0,
        'weight' => 1.5,
        'originCountry' => 'US',
        'tariffNumber' => '',
    ]]);
});

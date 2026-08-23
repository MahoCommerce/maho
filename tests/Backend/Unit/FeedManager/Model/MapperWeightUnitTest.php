<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

function weightFeed(string $platform = 'custom'): Maho_FeedManager_Model_Feed
{
    return Mage::getModel('feedmanager/feed')
        ->setPlatform($platform)
        ->setStoreId(1);
}

function weightProduct(): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('simple')
        ->setStoreId(1);
}

function weightUnit(string $unit): void
{
    Mage::app()->getStore(1)->setConfig('general/locale/weight_unit', $unit);
}

function resolveWeight(array $config, mixed $raw, string $platform = 'custom'): mixed
{
    $mapper = new Maho_FeedManager_Model_Mapper(weightFeed($platform));

    return $mapper->resolveFieldValue(
        ['source_type' => 'attribute', 'source_value' => 'weight'] + $config,
        ['weight' => $raw],
        weightProduct(),
    );
}

afterEach(function () {
    weightUnit('');
});

describe('Feed weight unit', function () {
    it('appends the store weight unit to a declared measure field', function () {
        weightUnit('kgs');

        expect(resolveWeight(['unit' => 'weight'], '2.5000'))->toBe('2.5 kg');
    });

    it('appends pounds when the store weighs in pounds', function () {
        weightUnit('lbs');

        expect(resolveWeight(['unit' => 'weight'], '2.5000'))->toBe('2.5 lb');
    });

    it('drops the trailing zeros of a whole weight', function () {
        weightUnit('kgs');

        expect(resolveWeight(['unit' => 'weight'], '3.0000'))->toBe('3 kg');
    });

    // A bare number is malformed, and Google reads a malformed value as missing. An empty
    // field reads as missing too, and it does not claim a unit the store never chose.
    it('exports nothing when the store declares no weight unit', function () {
        weightUnit('');

        expect(resolveWeight(['unit' => 'weight'], '2.5000'))->toBe('');
    });

    it('exports nothing for a unit the platforms do not accept', function () {
        weightUnit('t');

        expect(resolveWeight(['unit' => 'weight'], '2.5000'))->toBe('');
    });

    it('leaves a field that declares no measure alone', function () {
        weightUnit('kgs');

        expect(resolveWeight([], '2.5000'))->toBe('2.5000');
    });

    it('leaves a non numeric value alone', function () {
        weightUnit('kgs');

        expect(resolveWeight(['unit' => 'weight'], 'heavy'))->toBe('heavy');
    });

    // A feed saved before the declaration existed carries no unit key in its XML structure.
    it('falls back to the platform definition through the element tag', function () {
        weightUnit('kgs');

        expect(resolveWeight(['tag' => 'g:shipping_weight'], '2.5000', 'google'))->toBe('2.5 kg');
    });

    it('falls back through an unprefixed element tag', function () {
        weightUnit('kgs');

        expect(resolveWeight(['tag' => 'product_weight'], '2.5000', 'google'))->toBe('2.5 kg');
    });

    it('does not invent a measure for an unrelated tag', function () {
        weightUnit('kgs');

        expect(resolveWeight(['tag' => 'g:title'], '2.5000', 'google'))->toBe('2.5000');
    });
});

describe('Platform weight declarations', function () {
    it('declares the weight fields that need a unit', function () {
        $expected = [
            'google' => ['product_weight', 'shipping_weight'],
            'bing' => ['shipping_weight'],
            'facebook' => ['shipping_weight'],
            'pinterest' => ['shipping_weight'],
            'openai' => ['shipping_weight'],
        ];

        foreach ($expected as $platform => $fields) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($platform);
            expect($adapter)->not->toBeNull();

            foreach ($fields as $field) {
                expect($adapter->getUnitType($field))
                    ->toBe(Maho_FeedManager_Model_Mapper::UNIT_TYPE_WEIGHT, "$platform/$field");
            }
        }
    });

    it('carries the declaration into the default XML structure', function () {
        $rows = Maho_FeedManager_Model_Platform::getAdapter('google')->getDefaultXmlStructure();
        $weighted = array_filter($rows, static fn(array $r): bool => ($r['unit'] ?? '') === 'weight');

        $tags = array_map(static fn(array $r): string => $r['tag'], $weighted);
        sort($tags);

        expect($tags)->toBe(['g:product_weight', 'g:shipping_weight']);
    });
});

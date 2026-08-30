<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Declares one weight field that keeps the store unit and one that needs kilograms.
 */
class MixedUnitPlatformAdapter extends Maho_FeedManager_Model_Platform_AbstractAdapter
{
    protected string $_code = 'mixedunit';
    protected string $_name = 'Mixed Unit';
    protected array $_optionalAttributes = [
        'id' => ['label' => 'Id', 'required' => false],
        'shipping_weight' => ['label' => 'Shipping Weight', 'required' => false, 'unit' => 'weight'],
        'net_weight' => ['label' => 'Net Weight', 'required' => false, 'unit' => 'weight', 'unit_target' => 'kg'],
    ];
}

/**
 * Opens the weight note of the mapping tab, without a form and without the registry.
 */
class WeightNoteBlockProbe extends Maho_FeedManager_Block_Adminhtml_Feed_Edit_Tab_Mapping
{
    public function noteHtml(
        ?Maho_FeedManager_Model_Platform_AdapterInterface $platform,
        Maho_FeedManager_Model_Feed $feed,
    ): string {
        return $this->_getWeightUnitNoteHtml($platform, $feed);
    }
}

function fieldsFeed(array $data, string $platform = 'google'): Maho_FeedManager_Model_Feed
{
    $feed = Mage::getModel('feedmanager/feed');
    $feed->setData($data + ['store_id' => 1, 'platform' => $platform]);

    return $feed;
}

function fieldsCsvFeed(array $columns, string $platform = 'google'): Maho_FeedManager_Model_Feed
{
    return fieldsFeed([
        'file_format' => 'csv',
        'csv_columns' => json_encode(array_map(
            static fn(string $name): array => ['name' => $name, 'source_type' => 'attribute', 'source_value' => 'weight'],
            $columns,
        )),
    ], $platform);
}

function fieldsWeightUnit(string $unit): void
{
    Mage::app()->getStore(1)->setConfig('general/locale/weight_unit', $unit);
}

afterEach(function () {
    fieldsWeightUnit('');
});

describe('Exported fields without a mapper', function () {
    // The admin form holds no mapper, so it must read the same set as the generator.
    it('reads the CSV builder of the feed', function () {
        $feed = fieldsCsvFeed(['id', 'product_weight']);

        expect(Maho_FeedManager_Model_Feed_Fields::exported($feed))->toBe(['id', 'product_weight']);
    });

    it('reads the JSON builder of the feed', function () {
        $feed = fieldsFeed(['file_format' => 'json', 'json_structure' => json_encode([
            'shipping_weight' => ['type' => 'string', 'source_type' => 'attribute', 'source_value' => 'weight'],
        ])]);

        expect(Maho_FeedManager_Model_Feed_Fields::exported($feed))->toBe(['shipping_weight']);
    });

    it('reads a definition the merchant cannot save as JSON', function () {
        $feed = fieldsFeed(['file_format' => 'xml', 'xml_structure' => '{not json']);

        expect(Maho_FeedManager_Model_Feed_Fields::exported($feed))->toBe([]);
    });
});

describe('Weight note of the mapping tab', function () {
    it('names only the weight fields the feed exports', function () {
        fieldsWeightUnit('kgs');

        $note = (new WeightNoteBlockProbe())->noteHtml(
            Maho_FeedManager_Model_Platform::getAdapter('google'),
            fieldsCsvFeed(['id', 'shipping_weight']),
        );

        expect($note)->toContain('Shipping Weight');
        expect($note)->not->toContain('Product Weight');
    });

    it('says nothing when the feed exports no weight field', function () {
        fieldsWeightUnit('kgs');

        $note = (new WeightNoteBlockProbe())->noteHtml(
            Maho_FeedManager_Model_Platform::getAdapter('google'),
            fieldsCsvFeed(['id']),
        );

        expect($note)->toBe('');
    });

    it('reports the store that declares no weight unit', function () {
        $note = (new WeightNoteBlockProbe())->noteHtml(
            Maho_FeedManager_Model_Platform::getAdapter('google'),
            fieldsCsvFeed(['shipping_weight']),
        );

        expect($note)->toContain('error-msg');
        expect($note)->toContain('Shipping Weight');
    });

    // One message describes every field, so a field with its own target needs its own text.
    it('describes a converted field and a suffixed field apart', function () {
        fieldsWeightUnit('lbs');

        $note = (new WeightNoteBlockProbe())->noteHtml(
            new MixedUnitPlatformAdapter(),
            fieldsCsvFeed(['shipping_weight', 'net_weight'], 'custom'),
        );

        expect($note)->toContain('a number plus the store weight unit');
        expect($note)->toContain('Maho converts these fields from');
        expect(substr_count($note, 'Shipping Weight'))->toBe(1);
        expect(substr_count($note, 'Net Weight'))->toBe(1);
    });

    it('says that a matching store unit needs no conversion', function () {
        fieldsWeightUnit('kgs');

        $note = (new WeightNoteBlockProbe())->noteHtml(
            new MixedUnitPlatformAdapter(),
            fieldsCsvFeed(['net_weight'], 'custom'),
        );

        expect($note)->toContain('exports these fields unchanged');
    });
});

describe('Measure declaration of a platform', function () {
    // An adapter declares a measure in its attributes, so the interface asks for no method.
    it('keeps the measure lookup out of the adapter interface', function () {
        $methods = get_class_methods(Maho_FeedManager_Model_Platform_AdapterInterface::class);

        expect($methods)->not->toContain('getUnitType');
        expect($methods)->not->toContain('getUnitTarget');
    });

    it('reads the measure of a feed that declares no platform', function () {
        expect(Maho_FeedManager_Model_Mapper::unitTypeOf(null, 'shipping_weight'))->toBe('');
        expect(Maho_FeedManager_Model_Mapper::unitTargetOf(null, 'shipping_weight'))->toBe('');
    });

    it('reads the measure of an adapter that declares one', function () {
        $adapter = new MixedUnitPlatformAdapter();

        expect(Maho_FeedManager_Model_Mapper::unitTypeOf($adapter, 'net_weight'))
            ->toBe(Maho_FeedManager_Model_Mapper::UNIT_TYPE_WEIGHT);
        expect(Maho_FeedManager_Model_Mapper::unitTargetOf($adapter, 'net_weight'))->toBe('kg');
        expect(Maho_FeedManager_Model_Mapper::unitTargetOf($adapter, 'shipping_weight'))->toBe('');
        expect(Maho_FeedManager_Model_Mapper::unitTypeOf($adapter, 'id'))->toBe('');
    });
});

describe('Item template field syntax', function () {
    $bodies = static function (string $template): array {
        preg_match_all(Maho_FeedManager_Model_Feed_Fields::TEMPLATE_FIELD_PATTERN, $template, $m, PREG_SET_ORDER);

        return array_map(static fn(array $match): string => $match[1], $m);
    };

    it('reads a field and every parameter it carries', function () use ($bodies) {
        expect($bodies('<a>{type="attribute" value="weight" length="50"}</a>'))
            ->toBe(['type="attribute" value="weight" length="50"']);
    });

    it('reads each field of a line on its own', function () use ($bodies) {
        expect($bodies('<a>{type="attribute" value="x"}</a><b>{type="static" value="y"}</b>'))
            ->toBe(['type="attribute" value="x"', 'type="static" value="y"']);
    });

    it('keeps a closing brace that a value holds', function () use ($bodies) {
        expect($bodies('{type="static" value="a}b"}'))->toBe(['type="static" value="a}b"']);
    });

    it('reads no field from a value that holds a quote', function () use ($bodies) {
        expect($bodies('{type="static" value="a\\"b"}'))->toBe([]);
    });

    it('reads the parameters of the body it captured', function () use ($bodies) {
        $body = $bodies('<a>{type="attribute" value="weight" length="50"}</a>')[0];

        expect(Maho_FeedManager_Model_Feed_Fields::parseField($body))
            ->toBe(['type' => 'attribute', 'value' => 'weight', 'length' => '50']);
    });
});

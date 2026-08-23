<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Opens the output engine of the generator, without a file and without a product collection.
 */
class WeightUnitGeneratorProbe extends Maho_FeedManager_Model_Generator
{
    public function __construct(Maho_FeedManager_Model_Feed $feed)
    {
        $this->_feed = $feed;
        $this->_platform = Maho_FeedManager_Model_Platform::getAdapter((string) $feed->getPlatform());
        $this->_mapper = new Maho_FeedManager_Model_Mapper($feed);
        $this->_configureMapperFromBuilder();
        $this->_prepareOutputMode();
        $this->_errors = [];
    }

    /** @return array<int, string> */
    public function exportedFieldNames(): array
    {
        return Maho_FeedManager_Model_Feed_Fields::exported($this->_feed, $this->_mapper);
    }

    /** @return array<int, string> */
    public function measureErrors(): array
    {
        $this->_errors = [];
        $this->_checkMeasureUnits();

        return $this->_errors;
    }

    public function renderItem(Mage_Catalog_Model_Product $product): string
    {
        return $this->_renderItemTemplate((string) $this->_feed->getXmlItemTemplate(), $product, $this->_feed);
    }

    public function mapper(): Maho_FeedManager_Model_Mapper
    {
        return $this->_mapper;
    }
}

function generatorProbe(array $data, string $platform = 'google'): WeightUnitGeneratorProbe
{
    $feed = Mage::getModel('feedmanager/feed');
    $feed->setData($data + ['store_id' => 1, 'platform' => $platform]);

    return new WeightUnitGeneratorProbe($feed);
}

function generatorProduct(): Mage_Catalog_Model_Product
{
    return Mage::getModel('catalog/product')
        ->setId(1)
        ->setTypeId('simple')
        ->setStoreId(1)
        ->setSku('WEIGHT-1')
        ->setName('Weighted product')
        ->setWeight('2.5000');
}

function generatorWeightUnit(string $unit): void
{
    Mage::app()->getStore(1)->setConfig('general/locale/weight_unit', $unit);
}

afterEach(function () {
    generatorWeightUnit('');
});

describe('Feed fields that carry a weight', function () {
    it('names the element an XML structure keeps', function () {
        $probe = generatorProbe(['file_format' => 'xml', 'xml_structure' => json_encode([
            ['tag' => 'g:id', 'source_type' => 'attribute', 'source_value' => 'sku'],
            ['tag' => 'g:shipping', 'children' => [
                ['tag' => 'g:shipping_weight', 'source_type' => 'attribute', 'source_value' => 'weight'],
            ]],
        ])]);

        expect($probe->exportedFieldNames())->toBe(['g:id', 'g:shipping_weight']);
        expect($probe->measureErrors())->toHaveCount(1);
        expect($probe->measureErrors()[0])->toContain('Shipping Weight');
    });

    // The merchant owns the structure, so a field that is not in it is not a missing field.
    it('says nothing about an element the merchant removed', function () {
        $probe = generatorProbe(['file_format' => 'xml', 'xml_structure' => json_encode([
            ['tag' => 'g:id', 'source_type' => 'attribute', 'source_value' => 'sku'],
        ])]);

        expect($probe->exportedFieldNames())->toBe(['g:id']);
        expect($probe->measureErrors())->toBe([]);
    });

    it('names the property a JSON structure holds', function () {
        $probe = generatorProbe(['file_format' => 'json', 'json_structure' => json_encode([
            'id' => ['type' => 'string', 'source_type' => 'attribute', 'source_value' => 'sku'],
            'shipping' => ['type' => 'object', 'properties' => [
                'shipping_weight' => ['type' => 'string', 'source_type' => 'attribute', 'source_value' => 'weight'],
            ]],
        ])]);

        expect($probe->exportedFieldNames())->toBe(['id', 'shipping_weight']);
        expect($probe->measureErrors())->toHaveCount(1);
    });

    it('names the column a CSV builder holds', function () {
        $probe = generatorProbe(['file_format' => 'csv', 'csv_columns' => json_encode([
            ['name' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku'],
            ['name' => 'product_weight', 'source_type' => 'attribute', 'source_value' => 'weight'],
        ])]);

        expect($probe->exportedFieldNames())->toBe(['id', 'product_weight']);
        expect($probe->measureErrors()[0])->toContain('Product Weight');
    });

    it('names the platform defaults for a feed with no builder', function () {
        $probe = generatorProbe(['file_format' => 'csv']);

        expect($probe->exportedFieldNames())->toContain('product_weight', 'shipping_weight');
        expect($probe->measureErrors()[0])->toContain('Product Weight, Shipping Weight');
    });

    it('leaves out a field that writes no value', function () {
        $probe = generatorProbe(['file_format' => 'xml', 'xml_structure' => json_encode([
            ['tag' => 'g:shipping_weight', 'source_type' => 'attribute', 'source_value' => ''],
        ])]);

        expect($probe->exportedFieldNames())->toBe([]);
        expect($probe->measureErrors())->toBe([]);
    });

    it('says nothing when the store declares a weight unit', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe(['file_format' => 'csv']);

        expect($probe->measureErrors())->toBe([]);
    });
});

describe('Item template weight', function () {
    it('appends the store weight unit inside the element the platform declares', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe([
            'file_format' => 'xml',
            'xml_item_template' => '<g:shipping_weight>{type="attribute" value="weight"}</g:shipping_weight>',
        ]);

        expect($probe->renderItem(generatorProduct()))->toBe('<g:shipping_weight>2.5 kg</g:shipping_weight>');
    });

    it('appends the unit to the older placeholder syntax too', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe([
            'file_format' => 'xml',
            'xml_item_template' => '<g:product_weight>{{weight}}</g:product_weight>',
        ]);

        expect($probe->renderItem(generatorProduct()))->toBe('<g:product_weight>2.5 kg</g:product_weight>');
    });

    it('reads the element around a placeholder on its own line', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe([
            'file_format' => 'xml',
            'xml_item_template' => "<g:shipping_weight>\n    {type=\"attribute\" value=\"weight\"}\n</g:shipping_weight>",
        ]);

        expect($probe->renderItem(generatorProduct()))
            ->toBe("<g:shipping_weight>\n    2.5 kg\n</g:shipping_weight>");
    });

    // The merchant wrote the unit, so Maho leaves the stored value alone.
    it('leaves a placeholder that shares its element alone', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe([
            'file_format' => 'xml',
            'xml_item_template' => '<g:shipping_weight>{type="attribute" value="weight"} kg</g:shipping_weight>',
        ]);

        expect($probe->renderItem(generatorProduct()))->toBe('<g:shipping_weight>2.5000 kg</g:shipping_weight>');
    });

    it('leaves an element the platform does not declare alone', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe([
            'file_format' => 'xml',
            'xml_item_template' => '<weight_raw>{type="attribute" value="weight"}</weight_raw>',
        ]);

        expect($probe->renderItem(generatorProduct()))->toBe('<weight_raw>2.5000</weight_raw>');
    });

    it('names the weight elements of the template', function () {
        $probe = generatorProbe([
            'file_format' => 'xml',
            'xml_item_template' => "<g:id>{type=\"attribute\" value=\"sku\"}</g:id>\n"
                . '<g:shipping_weight>{type="attribute" value="weight"}</g:shipping_weight>',
        ]);

        expect($probe->exportedFieldNames())->toBe(['g:id', 'g:shipping_weight']);
        expect($probe->measureErrors()[0])->toContain('Shipping Weight');
    });
});

describe('Builder field names carry the measure', function () {
    it('applies the unit through the column name of a CSV builder', function () {
        generatorWeightUnit('kgs');

        $probe = generatorProbe(['file_format' => 'csv', 'csv_columns' => json_encode([
            ['name' => 'shipping_weight', 'source_type' => 'attribute', 'source_value' => 'weight'],
        ])]);

        expect($probe->mapper()->mapProduct(generatorProduct())['shipping_weight'])->toBe('2.5 kg');
    });

    it('applies the unit through the property name of a JSON structure', function () {
        generatorWeightUnit('kgs');

        $structure = [
            'shipping_weight' => ['type' => 'string', 'source_type' => 'attribute', 'source_value' => 'weight'],
        ];
        $probe = generatorProbe(['file_format' => 'json', 'json_structure' => json_encode($structure)]);

        expect($probe->mapper()->mapProductToJsonStructure(generatorProduct(), $structure))
            ->toBe(['shipping_weight' => '2.5 kg']);
    });

    it('converts through the column name for a platform that accepts one unit', function () {
        generatorWeightUnit('lbs');

        $probe = generatorProbe(['file_format' => 'csv', 'csv_columns' => json_encode([
            ['name' => 'Weight', 'source_type' => 'attribute', 'source_value' => 'weight'],
        ])], 'trovaprezzi');

        expect($probe->mapper()->mapProduct(generatorProduct())['Weight'])->toBe('1.134');
    });
});

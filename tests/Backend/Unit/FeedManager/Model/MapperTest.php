<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Mapper default mappings', function () {
    function getMapperMappings(Maho_FeedManager_Model_Feed $feed): array
    {
        $mapper = new Maho_FeedManager_Model_Mapper($feed);
        $property = new ReflectionProperty(Maho_FeedManager_Model_Mapper::class, '_mappings');
        return $property->getValue($mapper);
    }

    beforeEach(function () {
        $this->feed = Mage::getModel('feedmanager/feed');
        $this->feed->setPlatform('google');
        $this->feed->setStoreId(1);
    });

    test('platform defaults keep their transformer chains', function () {
        $mappings = getMapperMappings($this->feed);

        // Google default: availability maps is_in_stock 1/0 -> in_stock/out_of_stock
        expect($mappings)->toHaveKey('availability');
        expect($mappings['availability']['transformers'])->not->toBeEmpty();
        expect($mappings['availability']['transformers'][0]['transformer'])->toBe('conditional');
        expect($mappings['availability']['transformers'][0]['options']['true_value'])->toBe('in_stock');
    });

    test('platform defaults keep their use_parent mode', function () {
        $mappings = getMapperMappings($this->feed);

        // Google defaults: variant children link to the parent product page
        // and fall back to the parent image when they have none of their own
        expect($mappings['link']['use_parent'])->toBe('always');
        expect($mappings['image_link']['use_parent'])->toBe('if_empty');
    });

    test('db mappings are not overridden by platform defaults', function () {
        $this->feed->setName('Mapper Test Feed');
        $this->feed->setFilename('mapper-test-feed');
        $this->feed->save();
        Mage::getModel('feedmanager/attributeMapping')
            ->setFeedId((int) $this->feed->getId())
            ->setPlatformAttribute('availability')
            ->setSourceType('static')
            ->setSourceValue('in_stock')
            ->save();

        $mappings = getMapperMappings($this->feed);

        expect($mappings['availability']['source_type'])->toBe('static');
        expect($mappings['availability']['source_value'])->toBe('in_stock');
        expect($mappings['availability']['transformers'])->toBeEmpty();
    });
});

describe('One resolver for every output engine', function () {
    function firstSimpleProduct(): Mage_Catalog_Model_Product
    {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId(1)
            ->addAttributeToSelect('*')
            ->addFieldToFilter('type_id', 'simple')
            ->addPriceData()
            ->setPageSize(1)
            ->getFirstItem();

        $product->setStockItem(Mage::getModel('cataloginventory/stock_item')->loadByProduct($product));

        return $product;
    }

    /** Drive the xml_template engine for one known product. */
    function renderItemTemplate(Maho_FeedManager_Model_Feed $feed, Mage_Catalog_Model_Product $product, string $template): string
    {
        $generator = new Maho_FeedManager_Model_Generator();
        $reflection = new ReflectionObject($generator);
        $reflection->getProperty('_feed')->setValue($generator, $feed);
        $reflection->getProperty('_mapper')->setValue($generator, new Maho_FeedManager_Model_Mapper($feed));

        return $reflection->getMethod('_renderItemTemplate')->invoke($generator, $template, $product, $feed);
    }

    beforeEach(function () {
        $this->feed = Mage::getModel('feedmanager/feed')
            ->setPlatform('custom')
            ->setStoreId(1)
            ->setPriceCurrency('USD')
            ->setPriceCurrencySuffix(1)
            ->setTaxMode('excl');
    });

    test('all four engines return the same value for the same field', function () {
        // The list price differs from the discounted price, so an engine reading the wrong one
        // stands out. No tax class, so the tax mode cannot move the numbers either.
        $product = firstSimpleProduct();
        expect((int) $product->getId())->toBeGreaterThan(0);
        $product->setPrice(100.00)->setFinalPrice(80.00)->setTaxClassId(0);

        $mapper = new Maho_FeedManager_Model_Mapper($this->feed);
        $config = ['source_type' => 'attribute', 'source_value' => 'price'];

        $mapper->setMappingsFromCsvColumns([['name' => 'price'] + $config]);
        $flat = (string) $mapper->mapProduct($product)['price'];

        $json = $mapper->mapProductToJsonStructure($product, [
            'price' => ['type' => 'string'] + $config,
        ])['price'];

        $xml = $mapper->mapProductToXmlStructure($product, [
            ['tag' => 'price'] + $config,
        ], '', 0);
        $xmlValue = trim(strip_tags($xml));

        $template = renderItemTemplate($this->feed, $product, '{type="attribute" value="price"}');

        expect($json)->toBe($flat);
        expect($xmlValue)->toBe($flat);
        expect(trim($template))->toBe($flat);
    });

    test('every engine reports the discounted price, not the list price', function () {
        // No tax class, so the tax mode cannot move the numbers this test asserts on.
        $product = firstSimpleProduct();
        $product->setPrice(100.00)->setFinalPrice(80.00)->setTaxClassId(0);

        $mapper = new Maho_FeedManager_Model_Mapper($this->feed);
        $row = $mapper->extractProductData($product);

        expect((float) $row['price'])->toBe(80.00);
        expect((float) $row['final_price'])->toBe(80.00);
        expect((float) $row['regular_price'])->toBe(100.00);

        $template = renderItemTemplate($this->feed, $product, '{type="attribute" value="price"}');
        expect(trim($template))->toStartWith('80.00');
    });
});

<?php

declare(strict_types=1);
/**
 * Maho
 *
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('Product DTO', function () {
    it('has correct default values for all properties', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        expect($product->id)->toBeNull()
            ->and($product->sku)->toBe('')
            ->and($product->name)->toBe('')
            ->and($product->description)->toBeNull()
            ->and($product->shortDescription)->toBeNull()
            ->and($product->type)->toBe('simple')
            ->and($product->status)->toBe('enabled')
            ->and($product->visibility)->toBe('catalog_search')
            ->and($product->stockStatus)->toBe('in_stock')
            ->and($product->price)->toBeNull()
            ->and($product->specialPrice)->toBeNull()
            ->and($product->finalPrice)->toBeNull()
            ->and($product->stockQty)->toBeNull()
            ->and($product->weight)->toBeNull()
            ->and($product->barcode)->toBeNull()
            ->and($product->imageUrl)->toBeNull()
            ->and($product->smallImageUrl)->toBeNull()
            ->and($product->thumbnailUrl)->toBeNull()
            ->and($product->categoryIds)->toBe([])
            ->and($product->createdAt)->toBeNull()
            ->and($product->updatedAt)->toBeNull()
            ->and($product->configurableOptions)->toBe([])
            ->and($product->variants)->toBe([])
            ->and($product->hasRequiredOptions)->toBe(false)
            ->and($product->customOptions)->toBe([])
            ->and($product->reviewCount)->toBe(0)
            ->and($product->averageRating)->toBeNull();
    });
});

describe('Product DTO - property assignment', function () {
    it('can set all scalar properties', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->id = 123;
        $product->sku = 'TEST-SKU-001';
        $product->name = 'Test Product';
        $product->description = 'Full description';
        $product->shortDescription = 'Short description';
        $product->type = 'configurable';
        $product->status = 'disabled';
        $product->visibility = 'not_visible';
        $product->stockStatus = 'out_of_stock';
        $product->price = 99.99;
        $product->specialPrice = 79.99;
        $product->finalPrice = 79.99;
        $product->stockQty = 100.0;
        $product->weight = 5.5;
        $product->barcode = '1234567890123';
        $product->imageUrl = 'https://example.com/image.jpg';
        $product->smallImageUrl = 'https://example.com/small.jpg';
        $product->thumbnailUrl = 'https://example.com/thumb.jpg';
        $product->createdAt = '2025-01-15 10:30:00';
        $product->updatedAt = '2025-02-06 12:00:00';
        $product->hasRequiredOptions = true;
        $product->reviewCount = 42;
        $product->averageRating = 4.5;

        expect($product->id)->toBe(123)
            ->and($product->sku)->toBe('TEST-SKU-001')
            ->and($product->name)->toBe('Test Product')
            ->and($product->description)->toBe('Full description')
            ->and($product->shortDescription)->toBe('Short description')
            ->and($product->type)->toBe('configurable')
            ->and($product->status)->toBe('disabled')
            ->and($product->visibility)->toBe('not_visible')
            ->and($product->stockStatus)->toBe('out_of_stock')
            ->and($product->price)->toBe(99.99)
            ->and($product->specialPrice)->toBe(79.99)
            ->and($product->finalPrice)->toBe(79.99)
            ->and($product->stockQty)->toBe(100.0)
            ->and($product->weight)->toBe(5.5)
            ->and($product->barcode)->toBe('1234567890123')
            ->and($product->imageUrl)->toBe('https://example.com/image.jpg')
            ->and($product->smallImageUrl)->toBe('https://example.com/small.jpg')
            ->and($product->thumbnailUrl)->toBe('https://example.com/thumb.jpg')
            ->and($product->createdAt)->toBe('2025-01-15 10:30:00')
            ->and($product->updatedAt)->toBe('2025-02-06 12:00:00')
            ->and($product->hasRequiredOptions)->toBe(true)
            ->and($product->reviewCount)->toBe(42)
            ->and($product->averageRating)->toBe(4.5);
    });

    it('can set categoryIds array', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->categoryIds = [1, 2, 3, 5, 8];

        expect($product->categoryIds)->toBe([1, 2, 3, 5, 8])
            ->and($product->categoryIds)->toBeArray()
            ->and($product->categoryIds)->toHaveCount(5);
    });

    it('can set configurableOptions array', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->configurableOptions = [
            [
                'id' => 92,
                'code' => 'color',
                'label' => 'Color',
                'values' => [
                    ['value_index' => 1, 'label' => 'Red'],
                    ['value_index' => 2, 'label' => 'Blue'],
                ],
            ],
            [
                'id' => 180,
                'code' => 'size',
                'label' => 'Size',
                'values' => [
                    ['value_index' => 10, 'label' => 'Small'],
                    ['value_index' => 11, 'label' => 'Medium'],
                    ['value_index' => 12, 'label' => 'Large'],
                ],
            ],
        ];

        expect($product->configurableOptions)->toBeArray()
            ->and($product->configurableOptions)->toHaveCount(2)
            ->and($product->configurableOptions[0]['code'])->toBe('color')
            ->and($product->configurableOptions[1]['code'])->toBe('size')
            ->and($product->configurableOptions[0]['values'])->toHaveCount(2)
            ->and($product->configurableOptions[1]['values'])->toHaveCount(3);
    });

    it('can set variants array', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->variants = [
            ['id' => 10, 'sku' => 'TEST-RED-S'],
            ['id' => 11, 'sku' => 'TEST-RED-M'],
            ['id' => 12, 'sku' => 'TEST-BLUE-S'],
        ];

        expect($product->variants)->toBeArray()
            ->and($product->variants)->toHaveCount(3)
            ->and($product->variants[0]['sku'])->toBe('TEST-RED-S')
            ->and($product->variants[1]['id'])->toBe(11);
    });

    it('can set customOptions array', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->customOptions = [
            [
                'option_id' => 1,
                'title' => 'Custom Text',
                'type' => 'field',
                'is_require' => true,
                'price' => 10.00,
            ],
            [
                'option_id' => 2,
                'title' => 'Custom Dropdown',
                'type' => 'drop_down',
                'is_require' => false,
                'values' => [
                    ['title' => 'Option 1', 'price' => 5.00],
                    ['title' => 'Option 2', 'price' => 7.50],
                ],
            ],
        ];

        expect($product->customOptions)->toBeArray()
            ->and($product->customOptions)->toHaveCount(2)
            ->and($product->customOptions[0]['title'])->toBe('Custom Text')
            ->and($product->customOptions[1]['type'])->toBe('drop_down')
            ->and($product->customOptions[1]['values'])->toHaveCount(2);
    });
});

describe('Product - simple product mapping', function () {
    it('can load a simple product from database', function () {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        expect($product)->toBeInstanceOf(\Mage_Catalog_Model_Product::class)
            ->and($product->getId())->toBeNumeric()
            ->and($product->getId())->toBeGreaterThan(0)
            ->and($product->getTypeId())->toBe('simple')
            ->and($product->getSku())->toBeString()
            ->and($product->getSku())->not->toBeEmpty()
            ->and($product->getName())->toBeString()
            ->and($product->getName())->not->toBeEmpty();
    });

    it('has valid product type for simple products', function () {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('type_id', 'simple')
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        expect($product->getTypeId())->toBe('simple');
    });
});

describe('Product - stock data', function () {
    it('can retrieve stock information', function () {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        $stockItem = \Mage::getModel('cataloginventory/stock_item')
            ->loadByProduct($product);

        expect($stockItem)->toBeInstanceOf(\Mage_CatalogInventory_Model_Stock_Item::class)
            ->and($stockItem->getIsInStock())->toBeIn([0, 1, '0', '1', true, false, null]);

        $qty = $stockItem->getQty();
        if ($qty !== null) {
            expect($qty)->toBeNumeric();
        } else {
            expect($qty)->toBeNull();
        }
    });

    it('has numeric stock quantity', function () {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        $stockItem = \Mage::getModel('cataloginventory/stock_item')
            ->loadByProduct($product);
        $qty = $stockItem->getQty();

        if ($qty !== null) {
            expect($qty)->toBeNumeric();
        } else {
            expect($qty)->toBeNull();
        }
    });

    it('has valid in-stock status', function () {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        $stockItem = $product->getStockItem();
        $isInStock = $stockItem->getIsInStock();

        expect($isInStock)->toBeIn([0, 1, '0', '1', true, false]);
    });
});

describe('Product - configurable options structure', function () {
    it('accepts configurable options with expected structure', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $configurableOptions = [
            [
                'id' => 92,
                'code' => 'color',
                'label' => 'Color',
                'values' => [
                    ['value_index' => 1, 'label' => 'Red', 'price_adjustment' => 0],
                    ['value_index' => 2, 'label' => 'Blue', 'price_adjustment' => 5.00],
                    ['value_index' => 3, 'label' => 'Green', 'price_adjustment' => 2.50],
                ],
            ],
        ];

        $product->configurableOptions = $configurableOptions;

        expect($product->configurableOptions)->toBeArray()
            ->and($product->configurableOptions)->toHaveCount(1)
            ->and($product->configurableOptions[0])->toHaveKey('id')
            ->and($product->configurableOptions[0])->toHaveKey('code')
            ->and($product->configurableOptions[0])->toHaveKey('label')
            ->and($product->configurableOptions[0])->toHaveKey('values')
            ->and($product->configurableOptions[0]['values'])->toBeArray()
            ->and($product->configurableOptions[0]['values'])->toHaveCount(3);
    });

    it('supports multiple configurable options', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $configurableOptions = [
            [
                'id' => 92,
                'code' => 'color',
                'label' => 'Color',
                'values' => [
                    ['value_index' => 1, 'label' => 'Red'],
                    ['value_index' => 2, 'label' => 'Blue'],
                ],
            ],
            [
                'id' => 180,
                'code' => 'size',
                'label' => 'Size',
                'values' => [
                    ['value_index' => 10, 'label' => 'S'],
                    ['value_index' => 11, 'label' => 'M'],
                    ['value_index' => 12, 'label' => 'L'],
                    ['value_index' => 13, 'label' => 'XL'],
                ],
            ],
            [
                'id' => 250,
                'code' => 'material',
                'label' => 'Material',
                'values' => [
                    ['value_index' => 20, 'label' => 'Cotton'],
                    ['value_index' => 21, 'label' => 'Polyester'],
                ],
            ],
        ];

        $product->configurableOptions = $configurableOptions;

        expect($product->configurableOptions)->toHaveCount(3)
            ->and($product->configurableOptions[0]['code'])->toBe('color')
            ->and($product->configurableOptions[1]['code'])->toBe('size')
            ->and($product->configurableOptions[2]['code'])->toBe('material')
            ->and($product->configurableOptions[1]['values'])->toHaveCount(4);
    });

    it('handles empty configurable options array', function () {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->configurableOptions = [];

        expect($product->configurableOptions)->toBeArray()
            ->and($product->configurableOptions)->toHaveCount(0)
            ->and($product->configurableOptions)->toBe([]);
    });
});

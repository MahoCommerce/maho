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

describe('Product DTO - grouped products property', function (): void {
    it('has empty groupedProducts by default', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        expect($product->groupedProducts)->toBe([]);
    });

    it('can set groupedProducts array', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->groupedProducts = [
            [
                'id' => 10,
                'sku' => 'LUGGAGE-S',
                'name' => 'Small Luggage',
                'price' => 49.95,
                'finalPrice' => 49.95,
                'imageUrl' => 'https://example.com/thumb-small.jpg',
                'inStock' => true,
                'stockQty' => 25.0,
                'defaultQty' => 1.0,
                'position' => 1,
            ],
            [
                'id' => 11,
                'sku' => 'LUGGAGE-M',
                'name' => 'Medium Luggage',
                'price' => 79.95,
                'finalPrice' => 79.95,
                'imageUrl' => 'https://example.com/thumb-medium.jpg',
                'inStock' => true,
                'stockQty' => 10.0,
                'defaultQty' => 0.0,
                'position' => 2,
            ],
            [
                'id' => 12,
                'sku' => 'LUGGAGE-L',
                'name' => 'Large Luggage',
                'price' => 129.95,
                'finalPrice' => 129.95,
                'imageUrl' => null,
                'inStock' => false,
                'stockQty' => 0.0,
                'defaultQty' => 0.0,
                'position' => 3,
            ],
        ];

        expect($product->groupedProducts)->toBeArray()
            ->and($product->groupedProducts)->toHaveCount(3)
            ->and($product->groupedProducts[0]['sku'])->toBe('LUGGAGE-S')
            ->and($product->groupedProducts[0]['inStock'])->toBeTrue()
            ->and($product->groupedProducts[0]['defaultQty'])->toBe(1.0)
            ->and($product->groupedProducts[1]['name'])->toBe('Medium Luggage')
            ->and($product->groupedProducts[2]['inStock'])->toBeFalse()
            ->and($product->groupedProducts[2]['imageUrl'])->toBeNull();
    });

    it('handles empty groupedProducts array', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();
        $product->groupedProducts = [];

        expect($product->groupedProducts)->toBeArray()
            ->and($product->groupedProducts)->toHaveCount(0);
    });

    it('validates grouped product child structure has required keys', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();
        $product->groupedProducts = [
            [
                'id' => 10,
                'sku' => 'CHILD-SKU',
                'name' => 'Child Product',
                'price' => 29.95,
                'finalPrice' => 29.95,
                'imageUrl' => null,
                'inStock' => true,
                'stockQty' => 50.0,
                'defaultQty' => 0.0,
                'position' => 1,
            ],
        ];

        $child = $product->groupedProducts[0];
        expect($child)->toHaveKey('id')
            ->and($child)->toHaveKey('sku')
            ->and($child)->toHaveKey('name')
            ->and($child)->toHaveKey('price')
            ->and($child)->toHaveKey('finalPrice')
            ->and($child)->toHaveKey('imageUrl')
            ->and($child)->toHaveKey('inStock')
            ->and($child)->toHaveKey('stockQty')
            ->and($child)->toHaveKey('defaultQty')
            ->and($child)->toHaveKey('position');
    });
});

describe('Product DTO - bundle options property', function (): void {
    it('has empty bundleOptions by default', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        expect($product->bundleOptions)->toBe([]);
    });

    it('can set bundleOptions with selections', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $product->bundleOptions = [
            [
                'id' => 1,
                'title' => 'Bag',
                'type' => 'radio',
                'required' => true,
                'position' => 1,
                'selections' => [
                    [
                        'id' => 100,
                        'productId' => 50,
                        'sku' => 'BAG-SMALL',
                        'name' => 'Small Bag',
                        'price' => 0.0,
                        'priceType' => 'fixed',
                        'inStock' => true,
                        'isDefault' => true,
                        'canChangeQty' => false,
                        'defaultQty' => 1.0,
                        'position' => 1,
                    ],
                    [
                        'id' => 101,
                        'productId' => 51,
                        'sku' => 'BAG-LARGE',
                        'name' => 'Large Bag',
                        'price' => 15.00,
                        'priceType' => 'fixed',
                        'inStock' => true,
                        'isDefault' => false,
                        'canChangeQty' => true,
                        'defaultQty' => 1.0,
                        'position' => 2,
                    ],
                ],
            ],
            [
                'id' => 2,
                'title' => 'Accessories',
                'type' => 'checkbox',
                'required' => false,
                'position' => 2,
                'selections' => [
                    [
                        'id' => 200,
                        'productId' => 60,
                        'sku' => 'TAG',
                        'name' => 'Luggage Tag',
                        'price' => 5.00,
                        'priceType' => 'fixed',
                        'inStock' => true,
                        'isDefault' => false,
                        'canChangeQty' => false,
                        'defaultQty' => 1.0,
                        'position' => 1,
                    ],
                ],
            ],
        ];

        expect($product->bundleOptions)->toBeArray()
            ->and($product->bundleOptions)->toHaveCount(2)
            ->and($product->bundleOptions[0]['title'])->toBe('Bag')
            ->and($product->bundleOptions[0]['type'])->toBe('radio')
            ->and($product->bundleOptions[0]['required'])->toBeTrue()
            ->and($product->bundleOptions[0]['selections'])->toHaveCount(2)
            ->and($product->bundleOptions[0]['selections'][0]['isDefault'])->toBeTrue()
            ->and($product->bundleOptions[0]['selections'][1]['canChangeQty'])->toBeTrue()
            ->and($product->bundleOptions[1]['title'])->toBe('Accessories')
            ->and($product->bundleOptions[1]['type'])->toBe('checkbox')
            ->and($product->bundleOptions[1]['required'])->toBeFalse();
    });

    it('handles empty bundleOptions array', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();
        $product->bundleOptions = [];

        expect($product->bundleOptions)->toBeArray()
            ->and($product->bundleOptions)->toHaveCount(0);
    });

    it('validates bundle option structure has required keys', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();
        $product->bundleOptions = [
            [
                'id' => 1,
                'title' => 'Test Option',
                'type' => 'select',
                'required' => true,
                'position' => 1,
                'selections' => [],
            ],
        ];

        $option = $product->bundleOptions[0];
        expect($option)->toHaveKey('id')
            ->and($option)->toHaveKey('title')
            ->and($option)->toHaveKey('type')
            ->and($option)->toHaveKey('required')
            ->and($option)->toHaveKey('position')
            ->and($option)->toHaveKey('selections');
    });

    it('validates bundle selection structure has required keys', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();
        $product->bundleOptions = [
            [
                'id' => 1,
                'title' => 'Option',
                'type' => 'radio',
                'required' => true,
                'position' => 1,
                'selections' => [
                    [
                        'id' => 100,
                        'productId' => 50,
                        'sku' => 'SEL-SKU',
                        'name' => 'Selection Name',
                        'price' => 10.00,
                        'priceType' => 'percent',
                        'inStock' => true,
                        'isDefault' => false,
                        'canChangeQty' => true,
                        'defaultQty' => 2.0,
                        'position' => 1,
                    ],
                ],
            ],
        ];

        $selection = $product->bundleOptions[0]['selections'][0];
        expect($selection)->toHaveKey('id')
            ->and($selection)->toHaveKey('productId')
            ->and($selection)->toHaveKey('sku')
            ->and($selection)->toHaveKey('name')
            ->and($selection)->toHaveKey('price')
            ->and($selection)->toHaveKey('priceType')
            ->and($selection)->toHaveKey('inStock')
            ->and($selection)->toHaveKey('isDefault')
            ->and($selection)->toHaveKey('canChangeQty')
            ->and($selection)->toHaveKey('defaultQty')
            ->and($selection)->toHaveKey('position')
            ->and($selection['priceType'])->toBe('percent');
    });

    it('supports all bundle option types', function (): void {
        $product = new \Maho\ApiPlatform\ApiResource\Product();

        $types = ['select', 'radio', 'checkbox', 'multi'];
        $options = [];
        foreach ($types as $i => $type) {
            $options[] = [
                'id' => $i + 1,
                'title' => ucfirst($type) . ' Option',
                'type' => $type,
                'required' => true,
                'position' => $i + 1,
                'selections' => [],
            ];
        }
        $product->bundleOptions = $options;

        expect($product->bundleOptions)->toHaveCount(4)
            ->and($product->bundleOptions[0]['type'])->toBe('select')
            ->and($product->bundleOptions[1]['type'])->toBe('radio')
            ->and($product->bundleOptions[2]['type'])->toBe('checkbox')
            ->and($product->bundleOptions[3]['type'])->toBe('multi');
    });
});

describe('Product - grouped product loading from database', function (): void {
    it('can load a grouped product from database', function (): void {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('type_id', 'grouped')
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        // Skip if no grouped products exist in DB
        if (!$product->getId()) {
            expect(true)->toBeTrue();
            return;
        }

        expect($product)->toBeInstanceOf(\Mage_Catalog_Model_Product::class)
            ->and($product->getTypeId())->toBe('grouped');

        $typeInstance = $product->getTypeInstance(true);
        $associated = $typeInstance->getAssociatedProducts($product);

        expect($associated)->toBeArray();

        if (!empty($associated)) {
            $first = $associated[0];
            expect($first)->toBeInstanceOf(\Mage_Catalog_Model_Product::class)
                ->and($first->getId())->toBeNumeric()
                ->and($first->getSku())->toBeString()
                ->and($first->getName())->toBeString();
        }
    });
});

describe('Product - bundle product loading from database', function (): void {
    it('can load a bundle product from database', function (): void {
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToFilter('type_id', 'bundle')
            ->addAttributeToSelect('*')
            ->setPageSize(1)
            ->getFirstItem();

        // Skip if no bundle products exist in DB
        if (!$product->getId()) {
            expect(true)->toBeTrue();
            return;
        }

        expect($product)->toBeInstanceOf(\Mage_Catalog_Model_Product::class)
            ->and($product->getTypeId())->toBe('bundle');

        /** @var \Mage_Bundle_Model_Product_Type $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $optionsCollection = $typeInstance->getOptionsCollection($product);

        expect($optionsCollection)->not->toBeNull();

        if ($optionsCollection->getSize() > 0) {
            $optionIds = [];
            foreach ($optionsCollection as $option) {
                $optionIds[] = (int) $option->getId();
                expect($option->getTitle() ?: $option->getDefaultTitle())->toBeString();
                expect($option->getType())->toBeIn(['select', 'radio', 'checkbox', 'multi']);
            }

            // Load selections
            $selectionsCollection = $typeInstance->getSelectionsCollection($optionIds, $product);
            expect($selectionsCollection)->not->toBeNull();

            if ($selectionsCollection->getSize() > 0) {
                $first = $selectionsCollection->getFirstItem();
                expect($first->getSelectionId())->toBeNumeric()
                    ->and($first->getProductId())->toBeNumeric()
                    ->and($first->getName())->toBeString();
            }
        }
    });
});

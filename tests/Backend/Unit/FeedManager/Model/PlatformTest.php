<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Platform Factory', function () {
    test('returns all available platforms', function () {
        $platforms = Maho_FeedManager_Model_Platform::getAvailablePlatforms();
        expect($platforms)->toBeArray()
            ->and($platforms)->toContain('google')
            ->and($platforms)->toContain('google_local_inventory')
            ->and($platforms)->toContain('facebook')
            ->and($platforms)->toContain('bing')
            ->and($platforms)->toContain('pinterest')
            ->and($platforms)->toContain('idealo')
            ->and($platforms)->toContain('trovaprezzi')
            ->and($platforms)->toContain('openai')
            ->and($platforms)->toContain('custom')
            ->and($platforms)->toHaveCount(9);
    });

    test('getAdapter returns correct instance for each platform', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        expect($adapter)->toBeInstanceOf(Maho_FeedManager_Model_Platform_AdapterInterface::class)
            ->and($adapter)->toBeInstanceOf(Maho_FeedManager_Model_Platform_Google::class);
    });

    test('getAdapter returns null for nonexistent platform', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('nonexistent');
        expect($adapter)->toBeNull();
    });

    test('hasAdapter returns true for registered platforms', function () {
        expect(Maho_FeedManager_Model_Platform::hasAdapter('google'))->toBeTrue();
        expect(Maho_FeedManager_Model_Platform::hasAdapter('facebook'))->toBeTrue();
        expect(Maho_FeedManager_Model_Platform::hasAdapter('custom'))->toBeTrue();
    });

    test('hasAdapter returns false for unregistered platforms', function () {
        expect(Maho_FeedManager_Model_Platform::hasAdapter('nonexistent'))->toBeFalse();
        expect(Maho_FeedManager_Model_Platform::hasAdapter(''))->toBeFalse();
    });

    test('getPlatformOptions returns non-empty array with empty key', function () {
        $options = Maho_FeedManager_Model_Platform::getPlatformOptions();
        expect($options)->toBeArray()
            ->and($options)->not->toBeEmpty()
            ->and($options)->toHaveKey('')
            ->and($options)->toHaveKey('google')
            ->and($options)->toHaveKey('custom');
    });

    test('getPlatformFormats returns supported formats for google', function () {
        $formats = Maho_FeedManager_Model_Platform::getPlatformFormats('google');
        expect($formats)->toBeArray()
            ->and($formats)->not->toBeEmpty()
            ->and($formats)->toContain('xml');
    });

    test('getPlatformFormats returns default formats for nonexistent platform', function () {
        $formats = Maho_FeedManager_Model_Platform::getPlatformFormats('nonexistent');
        expect($formats)->toBeArray()
            ->and($formats)->toContain('xml')
            ->and($formats)->toContain('csv')
            ->and($formats)->toContain('json');
    });
});

describe('AdapterInterface compliance', function () {
    $platformCodes = [
        'google', 'google_local_inventory', 'facebook', 'bing',
        'pinterest', 'idealo', 'trovaprezzi', 'openai', 'custom',
    ];

    foreach ($platformCodes as $code) {
        test("{$code} implements AdapterInterface", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter)->toBeInstanceOf(Maho_FeedManager_Model_Platform_AdapterInterface::class);
        });

        test("{$code} getCode returns correct code", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getCode())->toBe($code);
        });

        test("{$code} getName returns non-empty string", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getName())->toBeString()->not->toBeEmpty();
        });

        test("{$code} getSupportedFormats returns non-empty array of strings", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $formats = $adapter->getSupportedFormats();
            expect($formats)->toBeArray()->not->toBeEmpty();
            foreach ($formats as $format) {
                expect($format)->toBeString()->toBeIn(['xml', 'csv', 'json', 'jsonl']);
            }
        });

        test("{$code} getDefaultFormat is in supported formats", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getDefaultFormat())->toBeIn($adapter->getSupportedFormats());
        });

        test("{$code} getRequiredAttributes returns array", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getRequiredAttributes())->toBeArray();
        });

        test("{$code} getOptionalAttributes returns array", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getOptionalAttributes())->toBeArray();
        });

        test("{$code} getAllAttributes includes all required attributes", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $all = $adapter->getAllAttributes();
            $required = $adapter->getRequiredAttributes();
            foreach (array_keys($required) as $key) {
                expect($all)->toHaveKey($key);
            }
        });

        test("{$code} getAllAttributes includes all optional attributes", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $all = $adapter->getAllAttributes();
            $optional = $adapter->getOptionalAttributes();
            foreach (array_keys($optional) as $key) {
                expect($all)->toHaveKey($key);
            }
        });

        test("{$code} getRootElement returns string", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getRootElement())->toBeString();
        });

        test("{$code} getItemElement returns string", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getItemElement())->toBeString();
        });

        test("{$code} getNamespaces returns array", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getNamespaces())->toBeArray();
        });

        test("{$code} getDefaultMappings returns array", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->getDefaultMappings())->toBeArray();
        });

        test("{$code} transformProductData returns array", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $result = $adapter->transformProductData(['title' => 'Test Product']);
            expect($result)->toBeArray();
        });

        test("{$code} validateProductData returns array", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $result = $adapter->validateProductData([]);
            expect($result)->toBeArray();
        });

        test("{$code} supportsCategoryMapping returns bool", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            expect($adapter->supportsCategoryMapping())->toBeBool();
        });
    }
});

describe('Google Platform', function () {
    test('required attributes include core shopping fields', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('id')
            ->and($required)->toHaveKey('title')
            ->and($required)->toHaveKey('description')
            ->and($required)->toHaveKey('link')
            ->and($required)->toHaveKey('price')
            ->and($required)->toHaveKey('image_link')
            ->and($required)->toHaveKey('availability')
            ->and($required)->toHaveKey('brand')
            ->and($required)->toHaveKey('google_product_category');
    });

    test('supported formats include xml', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        expect($adapter->getSupportedFormats())->toContain('xml');
    });

    test('root element is feed', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        expect($adapter->getRootElement())->toBe('feed');
    });

    test('item element is entry', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        expect($adapter->getItemElement())->toBe('entry');
    });

    test('has Atom and Google namespaces', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $ns = $adapter->getNamespaces();
        expect($ns)->toHaveKey('xmlns')
            ->and($ns)->toHaveKey('xmlns:g');
    });

    test('supports category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        expect($adapter->supportsCategoryMapping())->toBeTrue();
    });

    test('validates missing required attributes', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $errors = $adapter->validateProductData([]);
        expect($errors)->not->toBeEmpty();
    });

    test('validates valid product data with no extra errors', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $errors = $adapter->validateProductData([
            'id' => 'SKU-001',
            'title' => 'Test Product',
            'description' => 'A test product description',
            'link' => 'https://example.com/product',
            'image_link' => 'https://example.com/image.jpg',
            'availability' => 'in_stock',
            'price' => '25.00 USD',
            'brand' => 'TestBrand',
            'google_product_category' => '1234',
        ]);
        expect($errors)->toBeEmpty();
    });
});

describe('Facebook Platform', function () {
    test('supports xml and csv formats', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('facebook');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toContain('xml')
            ->and($formats)->toContain('csv');
    });

    test('required attributes include condition', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('facebook');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('condition');
    });

    test('item element is item', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('facebook');
        expect($adapter->getItemElement())->toBe('item');
    });
});

describe('Bing Platform', function () {
    test('supports xml and csv formats', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('bing');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toContain('xml')
            ->and($formats)->toContain('csv');
    });

    test('uses entry as item element', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('bing');
        expect($adapter->getItemElement())->toBe('entry');
    });

    test('supports category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('bing');
        expect($adapter->supportsCategoryMapping())->toBeTrue();
    });
});

describe('Pinterest Platform', function () {
    test('uses rss as root element', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('pinterest');
        expect($adapter->getRootElement())->toBe('rss');
    });

    test('uses item as item element', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('pinterest');
        expect($adapter->getItemElement())->toBe('item');
    });

    test('supports category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('pinterest');
        expect($adapter->supportsCategoryMapping())->toBeTrue();
    });
});

describe('Idealo Platform', function () {
    test('supports only csv format', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('idealo');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toBe(['csv']);
    });

    test('required attributes include sku, brand, eans', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('idealo');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('sku')
            ->and($required)->toHaveKey('brand')
            ->and($required)->toHaveKey('eans')
            ->and($required)->toHaveKey('delivery_time');
    });

    test('does not support category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('idealo');
        expect($adapter->supportsCategoryMapping())->toBeFalse();
    });
});

describe('Trovaprezzi Platform', function () {
    test('supports only xml format', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('trovaprezzi');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toBe(['xml']);
    });

    test('uses Products and Offer for XML elements', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('trovaprezzi');
        expect($adapter->getRootElement())->toBe('Products')
            ->and($adapter->getItemElement())->toBe('Offer');
    });

    test('required attributes include EanCode and Stock', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('trovaprezzi');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('EanCode')
            ->and($required)->toHaveKey('Stock');
    });

    test('does not support category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('trovaprezzi');
        expect($adapter->supportsCategoryMapping())->toBeFalse();
    });
});

describe('OpenAI Platform', function () {
    test('supports jsonl and csv formats', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('openai');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toContain('jsonl')
            ->and($formats)->toContain('csv');
    });

    test('default format is jsonl', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('openai');
        expect($adapter->getDefaultFormat())->toBe('jsonl');
    });

    test('required attributes include store_name and return_policy', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('openai');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('store_name')
            ->and($required)->toHaveKey('return_policy')
            ->and($required)->toHaveKey('return_window')
            ->and($required)->toHaveKey('group_id');
    });

    test('does not support category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('openai');
        expect($adapter->supportsCategoryMapping())->toBeFalse();
    });

    test('has no namespaces', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('openai');
        expect($adapter->getNamespaces())->toBeEmpty();
    });
});

describe('Custom Platform', function () {
    test('supports xml, csv, and json formats', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toContain('xml')
            ->and($formats)->toContain('csv')
            ->and($formats)->toContain('json');
    });

    test('has no required attributes', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        expect($adapter->getRequiredAttributes())->toBeEmpty();
    });

    test('does not support category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        expect($adapter->supportsCategoryMapping())->toBeFalse();
    });

    test('has no namespaces', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        expect($adapter->getNamespaces())->toBeEmpty();
    });

    test('validates product data with no errors', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        $errors = $adapter->validateProductData([]);
        expect($errors)->toBeEmpty();
    });

    test('uses products as root element', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        expect($adapter->getRootElement())->toBe('products');
    });

    test('uses product as item element', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        expect($adapter->getItemElement())->toBe('product');
    });
});

describe('Google Local Inventory Platform', function () {
    test('required attributes include store_code and availability', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google_local_inventory');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('id')
            ->and($required)->toHaveKey('store_code')
            ->and($required)->toHaveKey('availability');
    });

    test('supports xml and csv formats', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google_local_inventory');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toContain('xml')
            ->and($formats)->toContain('csv');
    });

    test('supports category mapping', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google_local_inventory');
        expect($adapter->supportsCategoryMapping())->toBeTrue();
    });
});

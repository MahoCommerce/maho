<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
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

        test("{$code} getAllAttributes includes all required attributes", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $all = $adapter->getAllAttributes();
            $required = $adapter->getRequiredAttributes();
            expect($all)->toBeArray();
            foreach (array_keys($required) as $key) {
                expect($all)->toHaveKey($key);
            }
        });

        test("{$code} getAllAttributes includes all optional attributes", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $all = $adapter->getAllAttributes();
            $optional = $adapter->getOptionalAttributes();
            expect($all)->toBeArray();
            foreach (array_keys($optional) as $key) {
                expect($all)->toHaveKey($key);
            }
        });

        test("{$code} transformProductData keeps a plain title", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $result = $adapter->transformProductData(['title' => 'Test Product']);
            expect($result['title'] ?? null)->toBe('Test Product');
        });

        test("{$code} validateProductData names each missing required attribute", function () use ($code) {
            $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
            $required = array_keys(array_filter($adapter->getRequiredAttributes(), fn(array $config): bool => !empty($config['required'])));
            $missing = array_map(fn(string $attribute): string => "Missing required attribute: {$attribute}", $required);
            $errors = $adapter->validateProductData([]);
            expect(array_slice($errors, 0, count($missing)))->toBe($missing);
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

    test('has Atom and Google namespaces', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $ns = $adapter->getNamespaces();
        expect($ns)->toHaveKey('xmlns')
            ->and($ns)->toHaveKey('xmlns:g');
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
    test('required attributes include condition', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('facebook');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('condition');
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
            ->and($required)->toHaveKey('delivery');
    });
});

describe('Trovaprezzi Platform', function () {
    test('supports only xml format', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('trovaprezzi');
        $formats = $adapter->getSupportedFormats();
        expect($formats)->toBe(['xml']);
    });

    test('required attributes include EanCode and Stock', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('trovaprezzi');
        $required = $adapter->getRequiredAttributes();
        expect($required)->toHaveKey('EanCode')
            ->and($required)->toHaveKey('Stock');
    });
});

describe('OpenAI Platform', function () {
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
});

describe('Custom Platform', function () {
    test('has no required attributes', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        expect($adapter->getRequiredAttributes())->toBeEmpty();
    });

    test('validates product data with no errors', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        $errors = $adapter->validateProductData([]);
        expect($errors)->toBeEmpty();
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

    test('namespaces every attribute, id included', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google_local_inventory');
        $namespaced = $adapter->getNamespacedAttributes();
        expect($namespaced)->toContain('id')
            ->and($namespaced)->toContain('price')
            ->and($namespaced)->toContain('store_code')
            ->and($namespaced)->toBe(array_keys($adapter->getAllAttributes()));
    });

    test('default XML structure prefixes every tag with g', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google_local_inventory');
        $tags = array_column($adapter->getDefaultXmlStructure(), 'tag');
        expect($tags)->toContain('g:id')
            ->and($tags)->toContain('g:store_code')
            ->and($tags)->toContain('g:availability')
            ->and($tags)->toContain('g:price')
            ->and($tags)->toContain('g:instore_product_location')
            ->and($tags)->not->toContain('price');
    });
});

describe('Default XML structure', function () {
    test('google keeps title and link unprefixed and prefixes id and price', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $structure = $adapter->getDefaultXmlStructure();
        $tags = array_column($structure, 'tag');
        expect($tags)->toContain('g:id')
            ->and($tags)->toContain('g:price')
            ->and($tags)->toContain('title')
            ->and($tags)->toContain('link')
            ->and($tags)->not->toContain('g:title');
    });

    test('carries source mapping, cdata and optional flags', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        $structure = array_column($adapter->getDefaultXmlStructure(), null, 'tag');

        expect($structure['g:id']['source_type'])->toBe('attribute')
            ->and($structure['g:id']['source_value'])->toBe('sku')
            ->and($structure['g:id']['optional'])->toBeFalse()
            ->and($structure['g:description']['cdata'])->toBeTrue()
            ->and($structure['g:description']['use_parent'])->toBe('if_empty')
            ->and($structure['g:gtin']['optional'])->toBeTrue();
    });

    test('platforms without namespaces keep plain tags', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        $structure = $adapter->getDefaultXmlStructure();
        expect($structure)->not->toBeEmpty();
        foreach ($structure as $row) {
            expect($row['tag'])->not->toStartWith('g:');
        }
    });
});

describe('Platform properties', function () {
    test('category mapping support', function (string $code, bool $expected) {
        expect(Maho_FeedManager_Model_Platform::getAdapter($code)->supportsCategoryMapping())->toBe($expected);
    })->with([
        'google' => ['google', true],
        'facebook' => ['facebook', true],
        'bing' => ['bing', true],
        'pinterest' => ['pinterest', true],
        'idealo' => ['idealo', false],
        'trovaprezzi' => ['trovaprezzi', false],
        'openai' => ['openai', false],
        'custom' => ['custom', false],
        'google_local_inventory' => ['google_local_inventory', false],
    ]);

    test('supported formats', function (string $code, array $formats) {
        expect(Maho_FeedManager_Model_Platform::getAdapter($code)->getSupportedFormats())->toContain(...$formats);
    })->with([
        'facebook' => ['facebook', ['xml', 'csv']],
        'bing' => ['bing', ['xml', 'csv']],
        'openai' => ['openai', ['jsonl', 'csv']],
        'custom' => ['custom', ['xml', 'csv', 'json']],
        'google_local_inventory' => ['google_local_inventory', ['xml', 'csv']],
    ]);

    test('root and item elements', function (string $code, string $root, string $item) {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter($code);
        expect($adapter->getRootElement())->toBe($root)
            ->and($adapter->getItemElement())->toBe($item);
    })->with([
        'google' => ['google', 'feed', 'entry'],
        'facebook' => ['facebook', 'feed', 'item'],
        'bing' => ['bing', 'feed', 'entry'],
        'pinterest' => ['pinterest', 'rss', 'item'],
        'trovaprezzi' => ['trovaprezzi', 'Products', 'Offer'],
        'custom' => ['custom', 'products', 'product'],
    ]);

    test('no namespaces', function (string $code) {
        expect(Maho_FeedManager_Model_Platform::getAdapter($code)->getNamespaces())->toBeEmpty();
    })->with(['openai', 'custom']);
});

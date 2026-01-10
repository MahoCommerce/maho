<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Platform Adapters', function () {
    test('can get list of available platforms', function () {
        $platforms = Maho_FeedManager_Model_Platform::getAvailablePlatforms();

        expect($platforms)->toBeArray();
        expect($platforms)->toContain('google');
        expect($platforms)->toContain('facebook');
        expect($platforms)->toContain('custom');
    });

    test('can get platform options for dropdown', function () {
        $options = Maho_FeedManager_Model_Platform::getPlatformOptions();

        expect($options)->toBeArray();
        expect($options)->toHaveKey('google');
        expect($options)->toHaveKey('facebook');
        expect($options['google'])->toBe('Google Shopping');
    });

    test('returns null for unknown platform', function () {
        $adapter = Maho_FeedManager_Model_Platform::getAdapter('nonexistent');
        expect($adapter)->toBeNull();
    });

    describe('Google Platform Adapter', function () {
        beforeEach(function () {
            $this->adapter = Maho_FeedManager_Model_Platform::getAdapter('google');
        });

        test('returns correct platform info', function () {
            expect($this->adapter->getCode())->toBe('google');
            expect($this->adapter->getName())->toBe('Google Shopping');
            expect($this->adapter->getSupportedFormats())->toBe(['xml']);
            expect($this->adapter->getRootElement())->toBe('feed');
            expect($this->adapter->getItemElement())->toBe('entry');
        });

        test('has required attributes defined', function () {
            $required = $this->adapter->getRequiredAttributes();

            expect($required)->toHaveKey('id');
            expect($required)->toHaveKey('title');
            expect($required)->toHaveKey('description');
            expect($required)->toHaveKey('link');
            expect($required)->toHaveKey('image_link');
            expect($required)->toHaveKey('price');
            expect($required)->toHaveKey('availability');
        });

        test('has optional attributes defined', function () {
            $optional = $this->adapter->getOptionalAttributes();

            expect($optional)->toHaveKey('gtin');
            expect($optional)->toHaveKey('mpn');
            expect($optional)->toHaveKey('color');
            expect($optional)->toHaveKey('size');
        });

        test('transforms availability correctly', function () {
            $data = ['availability' => '1'];
            $transformed = $this->adapter->transformProductData($data);
            expect($transformed['availability'])->toBe('in_stock');

            $data = ['availability' => '0'];
            $transformed = $this->adapter->transformProductData($data);
            expect($transformed['availability'])->toBe('out_of_stock');
        });

        test('transforms price with currency', function () {
            $data = ['price' => 99.99, 'currency' => 'AUD'];
            $transformed = $this->adapter->transformProductData($data);
            expect($transformed['price'])->toBe('99.99 AUD');
        });

        test('strips HTML from title', function () {
            $data = ['title' => '<b>Product</b> with <i>HTML</i>'];
            $transformed = $this->adapter->transformProductData($data);
            expect($transformed['title'])->toBe('Product with HTML');
        });

        test('truncates long titles', function () {
            $data = ['title' => str_repeat('A', 200)];
            $transformed = $this->adapter->transformProductData($data);
            expect(strlen($transformed['title']))->toBeLessThanOrEqual(150);
        });

        test('supports category mapping', function () {
            expect($this->adapter->supportsCategoryMapping())->toBeTrue();
        });
    });

    describe('Facebook Platform Adapter', function () {
        beforeEach(function () {
            $this->adapter = Maho_FeedManager_Model_Platform::getAdapter('facebook');
        });

        test('returns correct platform info', function () {
            expect($this->adapter->getCode())->toBe('facebook');
            expect($this->adapter->getName())->toBe('Facebook / Meta');
            expect($this->adapter->getSupportedFormats())->toBe(['xml', 'csv']);
        });

        test('transforms availability with spaces', function () {
            $data = ['availability' => '1'];
            $transformed = $this->adapter->transformProductData($data);
            expect($transformed['availability'])->toBe('in stock');

            $data = ['availability' => '0'];
            $transformed = $this->adapter->transformProductData($data);
            expect($transformed['availability'])->toBe('out of stock');
        });
    });

    describe('Custom Platform Adapter', function () {
        beforeEach(function () {
            $this->adapter = Maho_FeedManager_Model_Platform::getAdapter('custom');
        });

        test('supports all formats', function () {
            expect($this->adapter->getSupportedFormats())->toBe(['xml', 'csv', 'json']);
        });

        test('does not support category mapping', function () {
            expect($this->adapter->supportsCategoryMapping())->toBeFalse();
        });

        test('has no required attributes', function () {
            expect($this->adapter->getRequiredAttributes())->toBe([]);
        });
    });
});

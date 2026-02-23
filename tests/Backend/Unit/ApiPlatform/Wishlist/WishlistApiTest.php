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

describe('WishlistItem DTO', function (): void {
    it('has correct default values for all properties', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\WishlistItem();

        expect($dto->id)->toBeNull();
        expect($dto->productId)->toBeNull();
        expect($dto->productName)->toBeNull();
        expect($dto->productSku)->toBeNull();
        expect($dto->productPrice)->toBeNull();
        expect($dto->productImageUrl)->toBeNull();
        expect($dto->productUrl)->toBeNull();
        expect($dto->productType)->toBeNull();
        expect($dto->qty)->toBe(1);
        expect($dto->description)->toBeNull();
        expect($dto->addedAt)->toBeNull();
        expect($dto->inStock)->toBeTrue();
    });
});

describe('WishlistItem DTO - property assignment', function (): void {
    it('accepts all property values', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\WishlistItem();

        $dto->id = 42;
        $dto->productId = 123;
        $dto->productName = 'Test Product';
        $dto->productSku = 'TEST-SKU-001';
        $dto->productPrice = 99.99;
        $dto->productImageUrl = 'https://example.com/image.jpg';
        $dto->productUrl = 'https://example.com/product';
        $dto->productType = 'simple';
        $dto->qty = 5;
        $dto->description = 'Test description';
        $dto->addedAt = '2026-01-15 10:30:00';
        $dto->inStock = false;

        expect($dto->id)->toBe(42);
        expect($dto->productId)->toBe(123);
        expect($dto->productName)->toBe('Test Product');
        expect($dto->productSku)->toBe('TEST-SKU-001');
        expect($dto->productPrice)->toBe(99.99);
        expect($dto->productImageUrl)->toBe('https://example.com/image.jpg');
        expect($dto->productUrl)->toBe('https://example.com/product');
        expect($dto->productType)->toBe('simple');
        expect($dto->qty)->toBe(5);
        expect($dto->description)->toBe('Test description');
        expect($dto->addedAt)->toBe('2026-01-15 10:30:00');
        expect($dto->inStock)->toBeFalse();
    });

    it('can set null values for nullable properties', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\WishlistItem();

        // Set some values first
        $dto->id = 42;
        $dto->productName = 'Test';
        $dto->description = 'Description';

        // Then set them to null
        $dto->id = null;
        $dto->productName = null;
        $dto->description = null;

        expect($dto->id)->toBeNull();
        expect($dto->productName)->toBeNull();
        expect($dto->description)->toBeNull();
    });
});

describe('WishlistProvider - getProductImageUrl', function (): void {
    it('returns a string URL or empty string', function (): void {
        $provider = new \Maho\ApiPlatform\State\Provider\WishlistProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );

        // Load any product from DB
        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('small_image')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No products found in database');
        }

        // Use reflection to access private method
        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getProductImageUrl');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $product);

        expect($result)->toBeString();
    });

    it('returns URL containing http or empty string', function (): void {
        $provider = new \Maho\ApiPlatform\State\Provider\WishlistProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );

        $product = \Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('small_image')
            ->setPageSize(1)
            ->getFirstItem();

        if (!$product->getId()) {
            $this->markTestSkipped('No products found in database');
        }

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getProductImageUrl');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $product);

        if ($result !== '') {
            expect($result)->toContain('http');
        } else {
            expect($result)->toBe('');
        }
    });

    it('handles products without images gracefully', function (): void {
        $provider = new \Maho\ApiPlatform\State\Provider\WishlistProvider(
            $this->createMock(\Symfony\Bundle\SecurityBundle\Security::class),
        );

        // Create a product instance without proper initialization
        $product = \Mage::getModel('catalog/product');

        $reflection = new ReflectionClass($provider);
        $method = $reflection->getMethod('getProductImageUrl');
        $method->setAccessible(true);

        $result = $method->invoke($provider, $product);

        // Should return a string (URL or placeholder or empty)
        expect($result)->toBeString();
    });
});

describe('WishlistItem - buildWishlistItem mapping', function (): void {
    it('verifies DTO structure matches expected API output shape', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\WishlistItem();

        // Set all properties with realistic values
        $dto->id = 1;
        $dto->productId = 100;
        $dto->productName = 'Sample Product';
        $dto->productSku = 'SKU-123';
        $dto->productPrice = 49.99;
        $dto->productImageUrl = 'https://example.com/media/catalog/product/image.jpg';
        $dto->productUrl = 'https://example.com/catalog/product/view/id/100';
        $dto->productType = 'simple';
        $dto->qty = 2;
        $dto->description = 'Customer note';
        $dto->addedAt = '2026-02-01 12:00:00';
        $dto->inStock = true;

        // Verify all expected properties exist
        expect($dto)->toHaveProperty('id');
        expect($dto)->toHaveProperty('productId');
        expect($dto)->toHaveProperty('productName');
        expect($dto)->toHaveProperty('productSku');
        expect($dto)->toHaveProperty('productPrice');
        expect($dto)->toHaveProperty('productImageUrl');
        expect($dto)->toHaveProperty('productUrl');
        expect($dto)->toHaveProperty('productType');
        expect($dto)->toHaveProperty('qty');
        expect($dto)->toHaveProperty('description');
        expect($dto)->toHaveProperty('addedAt');
        expect($dto)->toHaveProperty('inStock');

        // Verify types match expected API contract
        expect($dto->id)->toBeInt();
        expect($dto->productId)->toBeInt();
        expect($dto->productName)->toBeString();
        expect($dto->productSku)->toBeString();
        expect($dto->productPrice)->toBeFloat();
        expect($dto->productImageUrl)->toBeString();
        expect($dto->productUrl)->toBeString();
        expect($dto->productType)->toBeString();
        expect($dto->qty)->toBeInt();
        expect($dto->description)->toBeString();
        expect($dto->addedAt)->toBeString();
        expect($dto->inStock)->toBeBool();
    });

    it('maintains correct types when values are null', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\WishlistItem();

        // Only set required/default values
        expect($dto->id)->toBeNull();
        expect($dto->productId)->toBeNull();
        expect($dto->productName)->toBeNull();
        expect($dto->productSku)->toBeNull();
        expect($dto->productPrice)->toBeNull();
        expect($dto->productImageUrl)->toBeNull();
        expect($dto->productUrl)->toBeNull();
        expect($dto->productType)->toBeNull();
        expect($dto->qty)->toBeInt();  // Has default value of 1
        expect($dto->description)->toBeNull();
        expect($dto->addedAt)->toBeNull();
        expect($dto->inStock)->toBeBool();  // Has default value of true
    });

    it('represents a complete wishlist item as returned by API', function (): void {
        $dto = new \Maho\ApiPlatform\ApiResource\WishlistItem();
        $dto->id = 5;
        $dto->productId = 200;
        $dto->productName = 'Tennis Racket';
        $dto->productSku = 'RACKET-001';
        $dto->productPrice = 199.95;
        $dto->productImageUrl = 'https://example.com/racket.jpg';
        $dto->productUrl = 'https://example.com/tennis-racket';
        $dto->productType = 'simple';
        $dto->qty = 1;
        $dto->description = 'Birthday gift';
        $dto->addedAt = '2026-02-06 14:30:00';
        $dto->inStock = true;

        // Verify this represents a complete, valid wishlist item
        expect($dto->id)->toBeGreaterThan(0);
        expect($dto->productId)->toBeGreaterThan(0);
        expect($dto->productName)->not->toBeEmpty();
        expect($dto->productSku)->not->toBeEmpty();
        expect($dto->productPrice)->toBeGreaterThan(0);
        expect($dto->qty)->toBeGreaterThan(0);
        expect($dto->productType)->toBeIn(['simple', 'configurable', 'grouped', 'bundle', 'virtual', 'downloadable']);
        expect($dto->inStock)->toBeBool();
    });
});

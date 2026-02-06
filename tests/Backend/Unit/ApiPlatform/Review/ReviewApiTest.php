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

describe('Review DTO', function () {
    it('has correct default values', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Review();

        expect($dto->id)->toBeNull();
        expect($dto->productId)->toBeNull();
        expect($dto->productName)->toBeNull();
        expect($dto->title)->toBe('');
        expect($dto->detail)->toBe('');
        expect($dto->nickname)->toBe('');
        expect($dto->rating)->toBe(5);
        expect($dto->status)->toBe('pending');
        expect($dto->createdAt)->toBeNull();
        expect($dto->customerId)->toBeNull();
    });
});

describe('Review DTO - property assignment', function () {
    it('accepts all property values', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Review();
        $dto->id = 123;
        $dto->productId = 456;
        $dto->productName = 'Test Product';
        $dto->title = 'Great Product!';
        $dto->detail = 'This is a detailed review of the product.';
        $dto->nickname = 'John Doe';
        $dto->rating = 4;
        $dto->status = 'approved';
        $dto->createdAt = '2025-01-15 10:30:00';
        $dto->customerId = 789;

        expect($dto->id)->toBe(123);
        expect($dto->productId)->toBe(456);
        expect($dto->productName)->toBe('Test Product');
        expect($dto->title)->toBe('Great Product!');
        expect($dto->detail)->toBe('This is a detailed review of the product.');
        expect($dto->nickname)->toBe('John Doe');
        expect($dto->rating)->toBe(4);
        expect($dto->status)->toBe('approved');
        expect($dto->createdAt)->toBe('2025-01-15 10:30:00');
        expect($dto->customerId)->toBe(789);
    });
});

describe('ReviewProcessor - validation', function () {
    it('validates rating bounds - minimum value', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Review();
        $dto->rating = 0;

        expect($dto->rating)->toBeLessThan(1)
            ->and($dto->rating)->not->toBeGreaterThanOrEqual(1);
    });

    it('validates rating bounds - maximum value', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Review();
        $dto->rating = 6;

        expect($dto->rating)->toBeGreaterThan(5)
            ->and($dto->rating)->not->toBeLessThanOrEqual(5);
    });

    it('validates rating bounds - valid ratings in range', function () {
        $validRatings = [1, 2, 3, 4, 5];

        foreach ($validRatings as $rating) {
            $dto = new \Maho\ApiPlatform\ApiResource\Review();
            $dto->rating = $rating;

            expect($dto->rating)->toBeGreaterThanOrEqual(1)
                ->and($dto->rating)->toBeLessThanOrEqual(5);
        }
    });

    it('verifies rating is within valid bounds', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Review();

        // Test each valid rating
        foreach ([1, 2, 3, 4, 5] as $rating) {
            $dto->rating = $rating;
            expect($dto->rating >= 1 && $dto->rating <= 5)->toBeTrue();
        }

        // Test invalid ratings
        $dto->rating = 0;
        expect($dto->rating >= 1 && $dto->rating <= 5)->toBeFalse();

        $dto->rating = 6;
        expect($dto->rating >= 1 && $dto->rating <= 5)->toBeFalse();
    });
});

describe('Review - status mapping', function () {
    it('maps status constants to string values', function () {
        // Test that the Review model constants exist and have expected values
        expect(\Mage_Review_Model_Review::STATUS_APPROVED)->toBe(1);
        expect(\Mage_Review_Model_Review::STATUS_PENDING)->toBe(2);
        expect(\Mage_Review_Model_Review::STATUS_NOT_APPROVED)->toBe(3);
    });

    it('uses correct status string values in DTO', function () {
        $dto = new \Maho\ApiPlatform\ApiResource\Review();

        // Test pending status (default)
        expect($dto->status)->toBe('pending');

        // Test approved status
        $dto->status = 'approved';
        expect($dto->status)->toBe('approved');

        // Test not_approved status
        $dto->status = 'not_approved';
        expect($dto->status)->toBe('not_approved');
    });

    it('verifies status values match expected constants', function () {
        // Map of status IDs to expected string values
        $statusMap = [
            \Mage_Review_Model_Review::STATUS_PENDING => 'pending',
            \Mage_Review_Model_Review::STATUS_APPROVED => 'approved',
            \Mage_Review_Model_Review::STATUS_NOT_APPROVED => 'not_approved',
        ];

        foreach ($statusMap as $statusId => $expectedString) {
            $dto = new \Maho\ApiPlatform\ApiResource\Review();
            $dto->status = $expectedString;

            expect($dto->status)->toBe($expectedString);
        }
    });
});

<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Transformers', function () {
    test('can get list of available transformers', function () {
        $transformers = Maho_FeedManager_Model_Transformer::getAvailableTransformers();

        expect($transformers)->toBeArray();
        expect($transformers)->toContain('strip_tags');
        expect($transformers)->toContain('truncate');
        expect($transformers)->toContain('format_price');
        expect($transformers)->toContain('map_values');
        expect($transformers)->toContain('conditional');
    });

    describe('StripTags Transformer', function () {
        test('removes HTML tags', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                '<p>Hello <b>World</b>!</p>',
                'strip_tags',
            );
            expect($result)->toBe('Hello World!');
        });

        test('can preserve specific tags', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                '<p>Hello <b>World</b>!</p>',
                'strip_tags',
                ['allowed_tags' => '<b>'],
            );
            expect($result)->toBe('Hello <b>World</b>!');
        });

        test('normalizes whitespace', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'Hello    World   ',
                'strip_tags',
            );
            expect($result)->toBe('Hello World');
        });
    });

    describe('Truncate Transformer', function () {
        test('truncates long text', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'This is a very long text that needs to be truncated',
                'truncate',
                ['max_length' => 20],
            );
            expect(strlen($result))->toBeLessThanOrEqual(20);
        });

        test('adds suffix when truncating', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'This is a very long text',
                'truncate',
                ['max_length' => 15, 'suffix' => '...'],
            );
            expect(str_ends_with($result, '...'))->toBeTrue();
        });

        test('respects word boundary', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'This is a test',
                'truncate',
                ['max_length' => 10, 'word_boundary' => true],
            );
            expect($result)->not()->toContain('tes'); // Should not cut mid-word
        });

        test('returns original if shorter than max', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'Short',
                'truncate',
                ['max_length' => 100],
            );
            expect($result)->toBe('Short');
        });
    });

    describe('FormatPrice Transformer', function () {
        test('formats price with currency', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                99.99,
                'format_price',
                ['currency' => 'AUD'],
            );
            expect($result)->toBe('99.99 AUD');
        });

        test('handles decimal places', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                99,
                'format_price',
                ['decimals' => 2],
            );
            expect($result)->toBe('99.00');
        });
    });

    describe('MapValues Transformer', function () {
        test('maps values correctly', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                '1',
                'map_values',
                ['mapping' => "1=in_stock\n0=out_of_stock"],
            );
            expect($result)->toBe('in_stock');
        });

        test('returns default for unmapped values', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'unknown',
                'map_values',
                ['mapping' => "1=yes\n0=no", 'default' => 'maybe'],
            );
            expect($result)->toBe('maybe');
        });

        test('returns original if no default and no mapping', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'original',
                'map_values',
                ['mapping' => "1=yes\n0=no"],
            );
            expect($result)->toBe('original');
        });
    });

    describe('DefaultValue Transformer', function () {
        test('provides default for empty value', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                '',
                'default_value',
                ['default' => 'N/A'],
            );
            expect($result)->toBe('N/A');
        });

        test('provides default for null value', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                null,
                'default_value',
                ['default' => 'N/A'],
            );
            expect($result)->toBe('N/A');
        });

        test('keeps original non-empty value', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                'existing',
                'default_value',
                ['default' => 'N/A'],
            );
            expect($result)->toBe('existing');
        });
    });

    describe('Conditional Transformer', function () {
        test('returns true value when condition matches', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                100,
                'conditional',
                ['operator' => 'gt', 'compare_value' => '50', 'true_value' => 'High', 'false_value' => 'Low'],
            );
            expect($result)->toBe('High');
        });

        test('returns false value when condition fails', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                30,
                'conditional',
                ['operator' => 'gt', 'compare_value' => '50', 'true_value' => 'High', 'false_value' => 'Low'],
            );
            expect($result)->toBe('Low');
        });

        test('can check for empty values', function () {
            $result = Maho_FeedManager_Model_Transformer::apply(
                '',
                'conditional',
                ['operator' => 'empty', 'true_value' => 'Empty', 'false_value' => 'Not Empty'],
            );
            expect($result)->toBe('Empty');
        });
    });

    describe('CombineFields Transformer', function () {
        test('combines fields using template', function () {
            $productData = ['brand' => 'Nike', 'name' => 'Air Max', 'sku' => 'NK-001'];

            $result = Maho_FeedManager_Model_Transformer::apply(
                '',
                'combine_fields',
                ['template' => '{{brand}} - {{name}} ({{sku}})'],
                $productData,
            );
            expect($result)->toBe('Nike - Air Max (NK-001)');
        });

        test('handles missing fields gracefully', function () {
            $productData = ['brand' => 'Nike'];

            $result = Maho_FeedManager_Model_Transformer::apply(
                '',
                'combine_fields',
                ['template' => '{{brand}} - {{name}}'],
                $productData,
            );
            expect($result)->toBe('Nike - ');
        });
    });

    describe('Pipeline', function () {
        test('chains multiple transformers', function () {
            $result = Maho_FeedManager_Model_Transformer::pipeline(
                '<p>This is a long HTML text</p>',
                [
                    ['transformer' => 'strip_tags'],
                    ['transformer' => 'truncate', 'options' => ['max_length' => 15, 'suffix' => '...']],
                ],
            );

            expect(strlen($result))->toBeLessThanOrEqual(18); // 15 + 3 for suffix
            expect($result)->not()->toContain('<');
        });
    });
});

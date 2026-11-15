<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * Create a test feed with attribute mappings and save to DB
 */
function createTestFeed(string $format): Maho_FeedManager_Model_Feed
{
    $feed = Mage::getModel('feedmanager/feed');
    $feed->setName('Integration Test Feed ' . strtoupper($format))
        ->setPlatform('custom')
        ->setStoreId(1)
        ->setIsEnabled(1)
        ->setFilename('test-integration-' . $format . '-' . uniqid())
        ->setFileFormat($format)
        ->setConfigurableMode('simple_only')
        ->setExcludeDisabled(1)
        ->setExcludeOutOfStock(0)
        ->save();

    // Create attribute mappings for the feed
    $mappings = [
        ['platform_attribute' => 'id', 'source_type' => 'attribute', 'source_value' => 'sku', 'sort_order' => 1],
        ['platform_attribute' => 'title', 'source_type' => 'attribute', 'source_value' => 'name', 'sort_order' => 2],
        ['platform_attribute' => 'price', 'source_type' => 'attribute', 'source_value' => 'price', 'sort_order' => 3],
        ['platform_attribute' => 'link', 'source_type' => 'attribute', 'source_value' => 'url_key', 'sort_order' => 4],
    ];

    foreach ($mappings as $mappingData) {
        $mapping = Mage::getModel('feedmanager/attributeMapping');
        $mapping->setFeedId((int) $feed->getId());
        foreach ($mappingData as $key => $value) {
            $mapping->setData($key, $value);
        }
        $mapping->save();
    }

    return $feed;
}

/**
 * The local path of the feed file. The media mount is local in the tests.
 */
function feedOutputPath(Maho_FeedManager_Model_Feed $feed): string
{
    return Mage::getBaseDir('media') . DS . $feed->getStoragePath();
}

/**
 * Clean up a test feed: generated file, logs, attribute mappings, and the feed itself
 */
function cleanupTestFeed(Maho_FeedManager_Model_Feed $feed): void
{
    // Delete generated file
    $outputPath = feedOutputPath($feed);
    if (file_exists($outputPath)) {
        @unlink($outputPath);
    }

    // Delete logs
    $logs = Mage::getResourceModel('feedmanager/log_collection')
        ->addFieldToFilter('feed_id', $feed->getId());
    foreach ($logs as $log) {
        $log->delete();
    }

    // Delete attribute mappings
    $mappings = Mage::getResourceModel('feedmanager/attributeMapping_collection')
        ->addFieldToFilter('feed_id', $feed->getId());
    foreach ($mappings as $mapping) {
        $mapping->delete();
    }

    // Delete feed
    $feed->delete();
}

describe('Feed Generation - CSV', function () {
    test('validates the generated feed with its delimiter and enclosure', function (string $delimiter, string $enclosure, string $title): void {
        $feed = createTestFeed('csv');

        try {
            $feed->setCsvDelimiter($delimiter)->setCsvEnclosure($enclosure)->save();
            $mapping = Mage::getResourceModel('feedmanager/attributeMapping_collection')
                ->addFieldToFilter('feed_id', $feed->getId())
                ->addFieldToFilter('platform_attribute', 'title')
                ->getFirstItem();
            $mapping->setSourceType(Maho_FeedManager_Model_Mapper::SOURCE_TYPE_STATIC)->setSourceValue($title)->save();

            $log = new Maho_FeedManager_Model_Generator()->generate($feed);

            expect($log->getStatus())->not->toBe('failed');
        } finally {
            cleanupTestFeed($feed);
        }
    })->with([
        'pipe delimiter' => ['|', '"', 'red,blue'],
        'no enclosure' => [',', '', '"red'],
    ]);
});

/**
 * Create a test feed pointing to a fresh non-existent output directory
 *
 * @return array{0: Maho_FeedManager_Model_Feed, 1: string}
 */
function createTestFeedInFreshDirectory(string $format, ?string $relativeDir = null): array
{
    $relativeDir ??= 'feeds/test-integration-' . uniqid();
    Mage::app()->getStore()->setConfig('feedmanager/general/output_directory', $relativeDir);

    $feed = createTestFeed($format);

    return [$feed, $relativeDir];
}

/**
 * Clean up a test feed and its temporary output directory
 */
function cleanupTestFeedAndDirectory(Maho_FeedManager_Model_Feed $feed, string $relativeDir): void
{
    cleanupTestFeed($feed);

    $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
    if (is_dir($absoluteDir)) {
        // Remove any remaining files
        $files = glob($absoluteDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        // Remove directory tree (deepest first)
        $dirs = [];
        $parts = explode('/', $relativeDir);
        $path = Mage::getBaseDir('media');
        foreach ($parts as $part) {
            $path .= DS . $part;
            $dirs[] = $path;
        }
        foreach (array_reverse($dirs) as $dir) {
            if (is_dir($dir)) {
                @rmdir($dir);
            }
        }
    }

    // Restore default config
    Mage::app()->getStore()->setConfig('feedmanager/general/output_directory', 'feeds');
}

describe('Feed Generation - Fresh Output Directory', function () {
    test('generates a feed into a directory that does not exist', function (string $format) {
        [$feed, $relativeDir] = createTestFeedInFreshDirectory($format);

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
            expect(is_dir($absoluteDir))->toBeTrue('Output directory should have been created');

            $outputPath = feedOutputPath($feed);
            expect(file_exists($outputPath))->toBeTrue('Feed file should exist on disk');
            expect(filesize($outputPath))->toBeGreaterThan(0);

            expect($log->getStatus())->toBe('completed');
            expect($log->getProductCount())->toBeGreaterThan(0);

            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, $format))->toBeTrue();

            $content = (string) file_get_contents($outputPath);
            match ($format) {
                'csv' => expect($content)->not->toBeEmpty(),
                'json' => expect(json_decode($content, true, flags: JSON_THROW_ON_ERROR)['products'] ?? [])->not->toBeEmpty(),
                'xml' => expect(simplexml_load_string($content))->not->toBeFalse(),
            };
        } finally {
            cleanupTestFeedAndDirectory($feed, $relativeDir);
        }
    })->with(['csv', 'json', 'xml']);

    test('generates feed into nested non-existent directory', function () {
        $relativeDir = 'feeds/deep/nested/test-' . uniqid();
        [$feed, $relativeDir] = createTestFeedInFreshDirectory('csv', $relativeDir);

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
            expect(is_dir($absoluteDir))->toBeTrue('Nested output directory should have been created recursively');

            $outputPath = feedOutputPath($feed);
            expect(file_exists($outputPath))->toBeTrue('Feed file should exist on disk');
            expect(filesize($outputPath))->toBeGreaterThan(0);

            expect($log->getStatus())->toBe('completed');
            expect($log->getProductCount())->toBeGreaterThan(0);

            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, 'csv'))->toBeTrue();
        } finally {
            cleanupTestFeedAndDirectory($feed, $relativeDir);
        }
    });
});

describe('Validator', function () {
    test('rejects non-existent file', function () {
        $validator = new Maho_FeedManager_Model_Validator();
        expect($validator->validate('/non/existent/file.csv', 'csv'))->toBeFalse();
        expect($validator->getErrors())->not()->toBeEmpty();
    });

    test('rejects empty file', function () {
        $tempFile = sys_get_temp_dir() . '/test_empty_' . uniqid() . '.csv';
        file_put_contents($tempFile, '');

        try {
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($tempFile, 'csv'))->toBeFalse();
            expect($validator->getErrors())->toContain('File is empty');
        } finally {
            @unlink($tempFile);
        }
    });

    test('rejects invalid JSON file', function () {
        $tempFile = sys_get_temp_dir() . '/test_invalid_' . uniqid() . '.json';
        file_put_contents($tempFile, '{"broken": [}');

        try {
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($tempFile, 'json'))->toBeFalse();
            expect($validator->getErrors())->not()->toBeEmpty();
        } finally {
            @unlink($tempFile);
        }
    });

    test('validates valid JSONL file', function () {
        $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.jsonl';
        file_put_contents($tempFile, "{\"id\":\"1\"}\n{\"id\":\"2\"}\n");

        try {
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($tempFile, 'jsonl'))->toBeTrue();
        } finally {
            @unlink($tempFile);
        }
    });
});

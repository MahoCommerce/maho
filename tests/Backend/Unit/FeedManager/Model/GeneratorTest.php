<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
        $mapping->setFeedId($feed->getId());
        foreach ($mappingData as $key => $value) {
            $mapping->setData($key, $value);
        }
        $mapping->save();
    }

    return $feed;
}

/**
 * Clean up a test feed: generated file, logs, attribute mappings, and the feed itself
 */
function cleanupTestFeed(Maho_FeedManager_Model_Feed $feed): void
{
    // Delete generated file
    $outputPath = $feed->getOutputFilePath();
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
    test('generates a valid CSV feed from real products', function () {
        $feed = createTestFeed('csv');

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            expect($log)->toBeInstanceOf(Maho_FeedManager_Model_Log::class);
            if ($log->getStatus() === 'failed') {
                $errors = $log->getErrorsArray();
                $messages = array_map(fn($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e, $errors);
                $errorMsg = implode("\n", array_slice($messages, 0, 5));
                throw new RuntimeException("CSV feed generation failed with errors:\n{$errorMsg}");
            }
            expect($log->getProductCount())->toBeGreaterThan(0);

            $outputPath = $feed->getOutputFilePath();
            expect(file_exists($outputPath))->toBeTrue();
            expect(filesize($outputPath))->toBeGreaterThan(0);

            // Validate the output with the Validator
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, 'csv'))->toBeTrue();
        } finally {
            cleanupTestFeed($feed);
        }
    });
});

describe('Feed Generation - JSON', function () {
    test('generates a valid JSON feed from real products', function () {
        $feed = createTestFeed('json');

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            expect($log)->toBeInstanceOf(Maho_FeedManager_Model_Log::class);
            if ($log->getStatus() === 'failed') {
                $errors = $log->getErrorsArray();
                $messages = array_map(fn($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e, $errors);
                $errorMsg = implode("\n", array_slice($messages, 0, 5));
                throw new RuntimeException("JSON feed generation failed with errors:\n{$errorMsg}");
            }
            expect($log->getProductCount())->toBeGreaterThan(0);

            $outputPath = $feed->getOutputFilePath();
            expect(file_exists($outputPath))->toBeTrue();
            expect(filesize($outputPath))->toBeGreaterThan(0);

            // Validate the output with the Validator
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, 'json'))->toBeTrue();

            // Verify JSON structure
            $content = file_get_contents($outputPath);
            $data = json_decode($content, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($data)->toHaveKey('products');
            expect(count($data['products']))->toBeGreaterThan(0);
        } finally {
            cleanupTestFeed($feed);
        }
    });
});

describe('Feed Generation - XML', function () {
    test('generates a valid XML feed from real products', function () {
        $feed = createTestFeed('xml');

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            expect($log)->toBeInstanceOf(Maho_FeedManager_Model_Log::class);
            if ($log->getStatus() === 'failed') {
                $errors = $log->getErrorsArray();
                $messages = array_map(fn($e) => is_array($e) ? ($e['message'] ?? json_encode($e)) : (string) $e, $errors);
                $errorMsg = implode("\n", array_slice($messages, 0, 5));
                throw new RuntimeException("XML feed generation failed with errors:\n{$errorMsg}");
            }
            expect($log->getProductCount())->toBeGreaterThan(0);

            $outputPath = $feed->getOutputFilePath();
            expect(file_exists($outputPath))->toBeTrue();
            expect(filesize($outputPath))->toBeGreaterThan(0);

            // Validate the output with the Validator
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, 'xml'))->toBeTrue();

            // Verify XML is parseable
            $content = file_get_contents($outputPath);
            $xml = simplexml_load_string($content);
            expect($xml)->not()->toBeFalse();
        } finally {
            cleanupTestFeed($feed);
        }
    });
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
    test('generates CSV feed into non-existent directory', function () {
        [$feed, $relativeDir] = createTestFeedInFreshDirectory('csv');

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
            expect(is_dir($absoluteDir))->toBeTrue('Output directory should have been created');

            $outputPath = $feed->getOutputFilePath();
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

    test('generates JSON feed into non-existent directory', function () {
        [$feed, $relativeDir] = createTestFeedInFreshDirectory('json');

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
            expect(is_dir($absoluteDir))->toBeTrue('Output directory should have been created');

            $outputPath = $feed->getOutputFilePath();
            expect(file_exists($outputPath))->toBeTrue('Feed file should exist on disk');
            expect(filesize($outputPath))->toBeGreaterThan(0);

            expect($log->getStatus())->toBe('completed');
            expect($log->getProductCount())->toBeGreaterThan(0);

            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, 'json'))->toBeTrue();

            $content = file_get_contents($outputPath);
            $data = json_decode($content, true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($data)->toHaveKey('products');
            expect(count($data['products']))->toBeGreaterThan(0);
        } finally {
            cleanupTestFeedAndDirectory($feed, $relativeDir);
        }
    });

    test('generates XML feed into non-existent directory', function () {
        [$feed, $relativeDir] = createTestFeedInFreshDirectory('xml');

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
            expect(is_dir($absoluteDir))->toBeTrue('Output directory should have been created');

            $outputPath = $feed->getOutputFilePath();
            expect(file_exists($outputPath))->toBeTrue('Feed file should exist on disk');
            expect(filesize($outputPath))->toBeGreaterThan(0);

            expect($log->getStatus())->toBe('completed');
            expect($log->getProductCount())->toBeGreaterThan(0);

            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($outputPath, 'xml'))->toBeTrue();

            $content = file_get_contents($outputPath);
            $xml = simplexml_load_string($content);
            expect($xml)->not()->toBeFalse();
        } finally {
            cleanupTestFeedAndDirectory($feed, $relativeDir);
        }
    });

    test('generates feed into nested non-existent directory', function () {
        $relativeDir = 'feeds/deep/nested/test-' . uniqid();
        [$feed, $relativeDir] = createTestFeedInFreshDirectory('csv', $relativeDir);

        try {
            $generator = new Maho_FeedManager_Model_Generator();
            $log = $generator->generate($feed);

            $absoluteDir = Mage::getBaseDir('media') . DS . $relativeDir;
            expect(is_dir($absoluteDir))->toBeTrue('Nested output directory should have been created recursively');

            $outputPath = $feed->getOutputFilePath();
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

    test('validates valid CSV file', function () {
        $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.csv';
        $handle = fopen($tempFile, 'w');
        fputcsv($handle, ['id', 'title', 'price']);
        fputcsv($handle, ['1', 'Product A', '10.00']);
        fclose($handle);

        try {
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($tempFile, 'csv'))->toBeTrue();
        } finally {
            @unlink($tempFile);
        }
    });

    test('validates valid JSON file', function () {
        $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.json';
        file_put_contents($tempFile, json_encode(['products' => [['id' => '1']]]));

        try {
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($tempFile, 'json'))->toBeTrue();
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

    test('validates valid XML file', function () {
        $tempFile = sys_get_temp_dir() . '/test_valid_' . uniqid() . '.xml';
        file_put_contents($tempFile, '<?xml version="1.0"?><feed><item><id>1</id></item></feed>');

        try {
            $validator = new Maho_FeedManager_Model_Validator();
            expect($validator->validate($tempFile, 'xml'))->toBeTrue();
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

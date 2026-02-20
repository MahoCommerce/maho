<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('CSV Writer', function () {
    test('reports correct format', function () {
        $writer = new Maho_FeedManager_Model_Writer_Csv();
        expect($writer->getFormat())->toBe('csv');
    });

    test('reports correct file extension', function () {
        $writer = new Maho_FeedManager_Model_Writer_Csv();
        expect($writer->getFileExtension())->toBe('csv');
    });

    test('reports correct mime type', function () {
        $writer = new Maho_FeedManager_Model_Writer_Csv();
        expect($writer->getMimeType())->toBe('text/csv');
    });

    test('writes valid CSV with header and data rows', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.csv';
        $writer = new Maho_FeedManager_Model_Writer_Csv();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Test Product', 'price' => '19.99']);
            $writer->writeProduct(['id' => '2', 'title' => 'Another Product', 'price' => '29.99']);
            $writer->close();

            expect(file_exists($tempFile))->toBeTrue();

            $lines = file($tempFile, FILE_IGNORE_NEW_LINES);
            expect(count($lines))->toBe(3); // header + 2 data rows

            $header = str_getcsv($lines[0]);
            expect($header)->toBe(['id', 'title', 'price']);

            $row1 = str_getcsv($lines[1]);
            expect($row1)->toBe(['1', 'Test Product', '19.99']);

            $row2 = str_getcsv($lines[2]);
            expect($row2)->toBe(['2', 'Another Product', '29.99']);
        } finally {
            @unlink($tempFile);
        }
    });

    test('can write CSV without header row', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.csv';
        $writer = new Maho_FeedManager_Model_Writer_Csv();
        $writer->setIncludeHeader(false);

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Product']);
            $writer->close();

            $lines = file($tempFile, FILE_IGNORE_NEW_LINES);
            expect(count($lines))->toBe(1); // no header, just data
            expect(str_getcsv($lines[0]))->toBe(['1', 'Product']);
        } finally {
            @unlink($tempFile);
        }
    });

    test('can use custom delimiter', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.csv';
        $writer = new Maho_FeedManager_Model_Writer_Csv();
        $writer->setDelimiter("\t");

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Product']);
            $writer->close();

            $content = file_get_contents($tempFile);
            expect($content)->toContain("\t");
        } finally {
            @unlink($tempFile);
        }
    });

    test('handles array values by joining with comma', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.csv';
        $writer = new Maho_FeedManager_Model_Writer_Csv();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'tags' => ['red', 'blue', 'green']]);
            $writer->close();

            $lines = file($tempFile, FILE_IGNORE_NEW_LINES);
            $row = str_getcsv($lines[1]);
            expect($row[1])->toBe('red,blue,green');
        } finally {
            @unlink($tempFile);
        }
    });
});

describe('JSON Writer', function () {
    test('reports correct format', function () {
        $writer = new Maho_FeedManager_Model_Writer_Json();
        expect($writer->getFormat())->toBe('json');
    });

    test('reports correct file extension', function () {
        $writer = new Maho_FeedManager_Model_Writer_Json();
        expect($writer->getFileExtension())->toBe('json');
    });

    test('reports correct mime type', function () {
        $writer = new Maho_FeedManager_Model_Writer_Json();
        expect($writer->getMimeType())->toBe('application/json');
    });

    test('writes valid JSON with products array', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.json';
        $writer = new Maho_FeedManager_Model_Writer_Json();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Test Product', 'price' => '19.99']);
            $writer->writeProduct(['id' => '2', 'title' => 'Another Product', 'price' => '29.99']);
            $writer->close();

            expect(file_exists($tempFile))->toBeTrue();

            $content = file_get_contents($tempFile);
            $data = json_decode($content, true);

            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($data)->toHaveKey('products');
            expect(count($data['products']))->toBe(2);
            expect($data['products'][0]['id'])->toBe('1');
            expect($data['products'][0]['title'])->toBe('Test Product');
            expect($data['products'][1]['id'])->toBe('2');
        } finally {
            @unlink($tempFile);
        }
    });

    test('can use custom root key', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.json';
        $writer = new Maho_FeedManager_Model_Writer_Json();
        $writer->setRootKey('items');

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Product']);
            $writer->close();

            $data = json_decode(file_get_contents($tempFile), true);
            expect($data)->toHaveKey('items');
            expect(count($data['items']))->toBe(1);
        } finally {
            @unlink($tempFile);
        }
    });

    test('produces valid JSON with empty product list', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.json';
        $writer = new Maho_FeedManager_Model_Writer_Json();

        try {
            $writer->open($tempFile);
            $writer->close();

            $data = json_decode(file_get_contents($tempFile), true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($data['products'])->toBe([]);
        } finally {
            @unlink($tempFile);
        }
    });
});

describe('XML Writer', function () {
    test('reports correct format', function () {
        $writer = new Maho_FeedManager_Model_Writer_Xml();
        expect($writer->getFormat())->toBe('xml');
    });

    test('reports correct file extension', function () {
        $writer = new Maho_FeedManager_Model_Writer_Xml();
        expect($writer->getFileExtension())->toBe('xml');
    });

    test('reports correct mime type', function () {
        $writer = new Maho_FeedManager_Model_Writer_Xml();
        expect($writer->getMimeType())->toBe('application/xml');
    });

    test('writes valid XML with product items', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.xml';
        $writer = new Maho_FeedManager_Model_Writer_Xml();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Test Product', 'price' => '19.99']);
            $writer->writeProduct(['id' => '2', 'title' => 'Another Product', 'price' => '29.99']);
            $writer->close();

            expect(file_exists($tempFile))->toBeTrue();

            $content = file_get_contents($tempFile);
            $xml = simplexml_load_string($content);

            expect($xml)->not()->toBeFalse();
            expect($xml->getName())->toBe('feed');
            expect(count($xml->item))->toBe(2);
            expect((string) $xml->item[0]->id)->toBe('1');
            expect((string) $xml->item[0]->title)->toBe('Test Product');
            expect((string) $xml->item[1]->id)->toBe('2');
        } finally {
            @unlink($tempFile);
        }
    });

    test('escapes XML special characters', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.xml';
        $writer = new Maho_FeedManager_Model_Writer_Xml();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Product <"Special"> & More']);
            $writer->close();

            $content = file_get_contents($tempFile);
            $xml = simplexml_load_string($content);

            expect($xml)->not()->toBeFalse();
            expect((string) $xml->item[0]->title)->toBe('Product <"Special"> & More');
        } finally {
            @unlink($tempFile);
        }
    });

    test('skips empty values', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.xml';
        $writer = new Maho_FeedManager_Model_Writer_Xml();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Product', 'description' => '', 'tags' => []]);
            $writer->close();

            $content = file_get_contents($tempFile);
            $xml = simplexml_load_string($content);

            expect(isset($xml->item[0]->description))->toBeFalse();
        } finally {
            @unlink($tempFile);
        }
    });
});

describe('JSONL Writer', function () {
    test('reports correct format', function () {
        $writer = new Maho_FeedManager_Model_Writer_Jsonl();
        expect($writer->getFormat())->toBe('jsonl');
    });

    test('reports correct file extension', function () {
        $writer = new Maho_FeedManager_Model_Writer_Jsonl();
        expect($writer->getFileExtension())->toBe('jsonl');
    });

    test('reports correct mime type', function () {
        $writer = new Maho_FeedManager_Model_Writer_Jsonl();
        expect($writer->getMimeType())->toBe('application/x-ndjson');
    });

    test('writes one JSON object per line', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.jsonl';
        $writer = new Maho_FeedManager_Model_Writer_Jsonl();

        try {
            $writer->open($tempFile);
            $writer->writeProduct(['id' => '1', 'title' => 'Test Product']);
            $writer->writeProduct(['id' => '2', 'title' => 'Another Product']);
            $writer->close();

            expect(file_exists($tempFile))->toBeTrue();

            $lines = array_filter(file($tempFile, FILE_IGNORE_NEW_LINES), fn($l) => $l !== '');
            expect(count($lines))->toBe(2);

            $obj1 = json_decode($lines[0], true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($obj1['id'])->toBe('1');
            expect($obj1['title'])->toBe('Test Product');

            $obj2 = json_decode($lines[1], true);
            expect(json_last_error())->toBe(JSON_ERROR_NONE);
            expect($obj2['id'])->toBe('2');
        } finally {
            @unlink($tempFile);
        }
    });
});

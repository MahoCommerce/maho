<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Writer metadata', function () {
    test('reports the format, the file extension and the mime type', function (string $class, string $format, string $extension, string $mimeType) {
        $writer = new $class();
        expect($writer->getFormat())->toBe($format)
            ->and($writer->getFileExtension())->toBe($extension)
            ->and($writer->getMimeType())->toBe($mimeType);
    })->with([
        'csv' => [Maho_FeedManager_Model_Writer_Csv::class, 'csv', 'csv', 'text/csv'],
        'json' => [Maho_FeedManager_Model_Writer_Json::class, 'json', 'json', 'application/json'],
        'xml' => [Maho_FeedManager_Model_Writer_Xml::class, 'xml', 'xml', 'application/xml'],
        'jsonl' => [Maho_FeedManager_Model_Writer_Jsonl::class, 'jsonl', 'jsonl', 'application/x-ndjson'],
    ]);
});

describe('CSV Writer', function () {
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

            expect(file_get_contents($tempFile))->toBe("id\ttitle\n1\tProduct\n");
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

describe('XML Writer', function () {
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

    test('prefixes Google Local Inventory elements with the g namespace', function () {
        $tempFile = sys_get_temp_dir() . '/test_feed_' . uniqid() . '.xml';
        $writer = new Maho_FeedManager_Model_Writer_Xml();
        $platform = Maho_FeedManager_Model_Platform::getAdapter('google_local_inventory');

        try {
            $writer->open($tempFile, $platform);
            $writer->writeProduct([
                'id' => 'SKU-001',
                'store_code' => 'STORE-1',
                'availability' => 'in_stock',
                'price' => '25.00 EUR',
                'quantity' => '5',
            ]);
            $writer->close();

            $content = file_get_contents($tempFile);

            expect($content)->toContain('<g:id>SKU-001</g:id>')
                ->and($content)->toContain('<g:store_code>STORE-1</g:store_code>')
                ->and($content)->toContain('<g:availability>in_stock</g:availability>')
                ->and($content)->toContain('<g:price>25.00 EUR</g:price>')
                ->and($content)->toContain('<g:quantity>5</g:quantity>')
                ->and($content)->not->toContain('<price>');
        } finally {
            @unlink($tempFile);
        }
    });
});

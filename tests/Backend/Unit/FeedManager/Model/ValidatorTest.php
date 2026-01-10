<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Validator', function () {
    beforeEach(function () {
        $this->validator = new Maho_FeedManager_Model_Validator();
        $this->testDir = sys_get_temp_dir() . '/feedmanager_test_' . uniqid();
        mkdir($this->testDir);
    });

    afterEach(function () {
        // Cleanup test files
        array_map('unlink', glob($this->testDir . '/*'));
        rmdir($this->testDir);
    });

    describe('XML Validation', function () {
        test('validates well-formed XML', function () {
            $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<feed>
    <item>
        <id>1</id>
        <title>Test Product</title>
    </item>
</feed>';
            $filePath = $this->testDir . '/test.xml';
            file_put_contents($filePath, $xmlContent);

            $result = $this->validator->validate($filePath, 'xml');

            expect($result)->toBeTrue();
            expect($this->validator->getErrors())->toBe([]);
        });

        test('fails on malformed XML', function () {
            $xmlContent = '<?xml version="1.0" encoding="UTF-8"?>
<feed>
    <item>
        <id>1</id>
        <title>Unclosed tag
    </item>
</feed>';
            $filePath = $this->testDir . '/bad.xml';
            file_put_contents($filePath, $xmlContent);

            $result = $this->validator->validate($filePath, 'xml');

            expect($result)->toBeFalse();
            expect(count($this->validator->getErrors()))->toBeGreaterThan(0);
        });

        test('fails on empty XML file', function () {
            $filePath = $this->testDir . '/empty.xml';
            file_put_contents($filePath, '');

            $result = $this->validator->validate($filePath, 'xml');

            expect($result)->toBeFalse();
        });
    });

    describe('CSV Validation', function () {
        test('validates well-formed CSV', function () {
            $csvContent = "id\ttitle\tprice\n1\tProduct One\t99.99\n2\tProduct Two\t149.99";
            $filePath = $this->testDir . '/test.csv';
            file_put_contents($filePath, $csvContent);

            $result = $this->validator->validate($filePath, 'csv');

            expect($result)->toBeTrue();
            expect($this->validator->getErrors())->toBe([]);
        });

        test('warns on column count mismatch', function () {
            $csvContent = "id\ttitle\tprice\n1\tProduct One\n2\tProduct Two\t149.99";
            $filePath = $this->testDir . '/mismatch.csv';
            file_put_contents($filePath, $csvContent);

            $result = $this->validator->validate($filePath, 'csv');

            // Should pass but with warnings
            expect($result)->toBeTrue();
            expect(count($this->validator->getWarnings()))->toBeGreaterThan(0);
        });

        test('fails on empty CSV file', function () {
            $filePath = $this->testDir . '/empty.csv';
            file_put_contents($filePath, '');

            $result = $this->validator->validate($filePath, 'csv');

            expect($result)->toBeFalse();
        });
    });

    describe('JSON Validation', function () {
        test('validates well-formed JSON', function () {
            $jsonContent = '{"products":[{"id":1,"title":"Product One"},{"id":2,"title":"Product Two"}]}';
            $filePath = $this->testDir . '/test.json';
            file_put_contents($filePath, $jsonContent);

            $result = $this->validator->validate($filePath, 'json');

            expect($result)->toBeTrue();
            expect($this->validator->getErrors())->toBe([]);
        });

        test('fails on malformed JSON', function () {
            $jsonContent = '{"products":[{"id":1,"title":"Missing bracket"]}';
            $filePath = $this->testDir . '/bad.json';
            file_put_contents($filePath, $jsonContent);

            $result = $this->validator->validate($filePath, 'json');

            expect($result)->toBeFalse();
            expect(count($this->validator->getErrors()))->toBeGreaterThan(0);
        });

        test('fails on trailing comma', function () {
            $jsonContent = '{"products":[{"id":1,}]}';
            $filePath = $this->testDir . '/comma.json';
            file_put_contents($filePath, $jsonContent);

            $result = $this->validator->validate($filePath, 'json');

            expect($result)->toBeFalse();
        });
    });

    test('fails for non-existent file', function () {
        $result = $this->validator->validate('/nonexistent/file.xml', 'xml');

        expect($result)->toBeFalse();
        expect($this->validator->getErrors())->toContain('File not found: /nonexistent/file.xml');
    });

    test('getAllMessages combines errors and warnings', function () {
        $csvContent = "id\ttitle\n1";  // Missing column
        $filePath = $this->testDir . '/mixed.csv';
        file_put_contents($filePath, $csvContent);

        $this->validator->validate($filePath, 'csv');
        $messages = $this->validator->getAllMessages();

        expect($messages)->toBeArray();
    });
});

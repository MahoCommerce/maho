<?php

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('FeedManager Gzip Compression', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir() . '/feedmanager_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->testFile = $this->tempDir . '/test_feed.xml';
        $this->testContent = '<?xml version="1.0"?><feed>' . str_repeat('<item>test</item>', 100) . '</feed>';
        file_put_contents($this->testFile, $this->testContent);
    });

    afterEach(function () {
        // Cleanup temp files
        $files = glob($this->tempDir . '/*');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    });

    test('gzip compression creates .gz file', function () {
        $gzPath = $this->testFile . '.gz';

        // Compress using same method as ProductProcessorTrait
        $source = fopen($this->testFile, 'rb');
        $dest = gzopen($gzPath, 'wb9');
        while (!feof($source)) {
            gzwrite($dest, fread($source, 1048576));
        }
        fclose($source);
        gzclose($dest);

        expect(file_exists($gzPath))->toBeTrue();
        expect(filesize($gzPath))->toBeGreaterThan(0);
    });

    test('gzip file is valid and decompressible', function () {
        $gzPath = $this->testFile . '.gz';

        // Compress
        $source = fopen($this->testFile, 'rb');
        $dest = gzopen($gzPath, 'wb9');
        while (!feof($source)) {
            gzwrite($dest, fread($source, 1048576));
        }
        fclose($source);
        gzclose($dest);

        // Decompress and verify content
        $decompressed = gzfile($gzPath);
        expect($decompressed)->not->toBeFalse();
        expect(implode('', $decompressed))->toBe($this->testContent);
    });

    test('gzip compression reduces file size', function () {
        $originalSize = filesize($this->testFile);
        $gzPath = $this->testFile . '.gz';

        // Compress
        $source = fopen($this->testFile, 'rb');
        $dest = gzopen($gzPath, 'wb9');
        while (!feof($source)) {
            gzwrite($dest, fread($source, 1048576));
        }
        fclose($source);
        gzclose($dest);

        $compressedSize = filesize($gzPath);
        expect($compressedSize)->toBeLessThan($originalSize);
    });

    test('feed model has gzip_compression attribute', function () {
        $feed = Mage::getModel('feedmanager/feed');
        $feed->setGzipCompression(1);

        expect($feed->getGzipCompression())->toBe(1);

        $feed->setGzipCompression(0);
        expect($feed->getGzipCompression())->toBe(0);
    });

    test('feed URL includes .gz extension when gzip enabled', function () {
        $feed = Mage::getModel('feedmanager/feed');
        $feed->setFilename('test_feed');
        $feed->setFileFormat('xml');
        $feed->setGzipCompression(0);

        $urlWithoutGzip = Mage::helper('feedmanager')->getFeedUrl($feed);
        expect($urlWithoutGzip)->toEndWith('test_feed.xml');

        $feed->setGzipCompression(1);
        $urlWithGzip = Mage::helper('feedmanager')->getFeedUrl($feed);
        expect($urlWithGzip)->toEndWith('test_feed.xml.gz');
    });

    test('gzip setting can be saved and loaded from database', function () {
        $feed = Mage::getModel('feedmanager/feed');
        $feed->setName('Gzip Test Feed');
        $feed->setFilename('gzip_test');
        $feed->setPlatform('google');
        $feed->setFileFormat('xml');
        $feed->setStoreId(1);
        $feed->setIsEnabled(1);
        $feed->setGzipCompression(1);
        $feed->save();

        $feedId = $feed->getId();
        expect($feedId)->toBeGreaterThan(0);

        // Load fresh instance
        $loadedFeed = Mage::getModel('feedmanager/feed')->load($feedId);
        expect((int) $loadedFeed->getGzipCompression())->toBe(1);

        // Cleanup
        $loadedFeed->delete();
    });
});

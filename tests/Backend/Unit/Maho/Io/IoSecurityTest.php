<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

uses(Tests\MahoBackendTestCase::class);

describe('\Maho\Io Security Methods', function () {
    describe('validatePath()', function () {
        beforeEach(function () {
            $this->testDir = sys_get_temp_dir() . '/maho_io_test_' . uniqid();
            mkdir($this->testDir, 0755, true);
            $this->testFile = $this->testDir . '/test.txt';
            file_put_contents($this->testFile, 'test content');
        });

        afterEach(function () {
            if (file_exists($this->testFile)) {
                unlink($this->testFile);
            }
            if (is_dir($this->testDir)) {
                rmdir($this->testDir);
            }
        });

        it('returns real path for valid file', function () {
            $result = \Maho\Io::validatePath($this->testFile);
            expect($result)->toBe(realpath($this->testFile));
        });

        it('returns false for non-existent file', function () {
            $result = \Maho\Io::validatePath($this->testDir . '/nonexistent.txt');
            expect($result)->toBeFalse();
        });

        it('returns false for phar:// path', function () {
            $result = \Maho\Io::validatePath('phar://malicious.phar');
            expect($result)->toBeFalse();
        });

        it('returns false for http:// path', function () {
            $result = \Maho\Io::validatePath('http://example.com/file');
            expect($result)->toBeFalse();
        });

        it('validates path within allowed base directory', function () {
            $result = \Maho\Io::validatePath($this->testFile, $this->testDir);
            expect($result)->toBe(realpath($this->testFile));
        });

        it('returns false when path escapes base directory', function () {
            $result = \Maho\Io::validatePath('/etc/passwd', $this->testDir);
            expect($result)->toBeFalse();
        });

        it('returns false when base directory has stream wrapper', function () {
            $result = \Maho\Io::validatePath($this->testFile, 'phar://malicious.phar');
            expect($result)->toBeFalse();
        });

        it('returns false when base directory does not exist', function () {
            $result = \Maho\Io::validatePath($this->testFile, '/nonexistent/directory');
            expect($result)->toBeFalse();
        });

        it('handles path traversal attempts', function () {
            $result = \Maho\Io::validatePath($this->testDir . '/../../../etc/passwd', $this->testDir);
            expect($result)->toBeFalse();
        });
    });

    describe('allowedPath()', function () {
        beforeEach(function () {
            $this->testDir = sys_get_temp_dir() . '/maho_io_test_' . uniqid();
            mkdir($this->testDir, 0755, true);
        });

        afterEach(function () {
            if (is_dir($this->testDir)) {
                rmdir($this->testDir);
            }
        });

        it('allows non-existent path within base directory', function () {
            $nonExistentPath = $this->testDir . '/future/sitemap/';
            expect(\Maho\Io::allowedPath($nonExistentPath, $this->testDir))->toBeTrue();
        });

        it('returns false for phar:// path', function () {
            expect(\Maho\Io::allowedPath('phar://malicious.phar', $this->testDir))->toBeFalse();
        });

        it('returns false for phar:// base directory', function () {
            expect(\Maho\Io::allowedPath('/some/path', 'phar://malicious.phar'))->toBeFalse();
        });

        it('blocks path traversal with ../', function () {
            expect(\Maho\Io::allowedPath($this->testDir . '/../../../etc/passwd', $this->testDir))->toBeFalse();
        });

        it('allows path exactly matching base directory', function () {
            expect(\Maho\Io::allowedPath($this->testDir, $this->testDir))->toBeTrue();
        });

        it('allows subdirectory path', function () {
            expect(\Maho\Io::allowedPath($this->testDir . '/subdir/file.xml', $this->testDir))->toBeTrue();
        });
    });

    describe('getImageSize()', function () {
        beforeEach(function () {
            $this->testDir = sys_get_temp_dir() . '/maho_io_test_' . uniqid();
            mkdir($this->testDir, 0755, true);

            // Create a minimal valid PNG (1x1 pixel)
            $this->testImage = $this->testDir . '/test.png';
            $img = imagecreatetruecolor(1, 1);
            imagepng($img, $this->testImage);
            imagedestroy($img);
        });

        afterEach(function () {
            if (file_exists($this->testImage)) {
                unlink($this->testImage);
            }
            if (is_dir($this->testDir)) {
                rmdir($this->testDir);
            }
        });

        it('returns image size for valid image', function () {
            $result = \Maho\Io::getImageSize($this->testImage);
            expect($result)->toBeArray();
            expect($result[0])->toBe(1); // width
            expect($result[1])->toBe(1); // height
        });

        it('returns false for phar:// path', function () {
            $result = \Maho\Io::getImageSize('phar://malicious.phar');
            expect($result)->toBeFalse();
        });

        it('returns false for non-existent file', function () {
            $result = \Maho\Io::getImageSize('/nonexistent/image.png');
            expect($result)->toBeFalse();
        });

        it('returns false for http:// path', function () {
            $result = \Maho\Io::getImageSize('http://example.com/image.png');
            expect($result)->toBeFalse();
        });
    });
})->group('security');

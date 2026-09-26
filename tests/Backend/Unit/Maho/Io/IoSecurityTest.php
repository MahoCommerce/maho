<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('\Maho\Io Security Methods', function () {
    describe('getImageSize()', function () {
        beforeEach(function () {
            $this->testDir = sys_get_temp_dir() . '/maho_io_test_' . uniqid();
            mkdir($this->testDir, 0755, true);

            // Create a minimal valid PNG (1x1 pixel)
            $this->testImage = $this->testDir . '/test.png';
            $img = imagecreatetruecolor(1, 1);
            imagepng($img, $this->testImage);
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

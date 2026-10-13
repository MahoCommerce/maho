<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

describe('Maho::buildImageResizeCachePath', function () {
    beforeEach(function () {
        $this->baseMediaPath = Mage::getBaseDir('media') . '/catalog/product';
        $this->params = [
            '_width' => 800,
            '_height' => null,
            '_quality' => 80,
            '_keepAspectRatio' => true,
            '_keepFrame' => false,
            '_keepTransparency' => true,
            '_constrainOnly' => true,
            '_backgroundColorStr' => 'ffffff',
            '_destinationSubdir' => 'image',
            '_angle' => 0,
        ];
    });

    it('gives a different cache path to two source files that differ only by extension', function () {
        $fromJpg = Maho::buildImageResizeCachePath($this->params, $this->baseMediaPath, '/i/m/image_76.jpg');
        $fromPng = Maho::buildImageResizeCachePath($this->params, $this->baseMediaPath, '/i/m/image_76.png');

        expect($fromJpg)->not->toBe($fromPng);
    });

    it('keeps the source file name and appends the configured extension', function () {
        $path = Maho::buildImageResizeCachePath($this->params, $this->baseMediaPath, '/i/m/image_76.jpg');

        expect($path)->toEndWith('/i/m/image_76.jpg' . Maho::getConfiguredImageExtension());
    });

    it('keeps the cache path of earlier releases, so existing cache files stay valid', function () {
        Mage::app()->setCurrentStore(1);

        $path = Maho::buildImageResizeCachePath($this->params, 'catalog/product', '/i/m/image_76.jpg');

        expect($path)->toBe('catalog/product/cache/1/image/800x/e3f29957c4a84f7bf5c1685080c2b7a2/i/m/image_76.jpg' . Maho::getConfiguredImageExtension());
    });

    it('appends the configured extension to a source file that has no extension', function () {
        $path = Maho::buildImageResizeCachePath($this->params, $this->baseMediaPath, '/i/m/image_76');

        expect($path)->toEndWith('/i/m/image_76' . Maho::getConfiguredImageExtension());
    });
});

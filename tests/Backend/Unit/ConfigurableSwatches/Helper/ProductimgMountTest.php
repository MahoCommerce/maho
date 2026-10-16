<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;
use Tests\Helpers\RemoteFilesystemAdapter;

uses(Tests\MahoBackendTestCase::class);

describe('Mage_ConfigurableSwatches_Helper_Productimg on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_swatches_' . uniqid();
        mkdir($this->root, 0777, true);
        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());
        $this->prefix = 'catalog/swatches/' . Mage::app()->getStore()->getId() . '/20x20/';

        $this->helper = new class extends Mage_ConfigurableSwatches_Helper_Productimg {
            public function resize(string $filename, string $tag): string|false
            {
                return $this->_resizeSwatchImage($filename, $tag, 20, 20);
            }
        };
        $this->useMount = function (FilesystemAdapter $adapter): Mount {
            $mount = new Mount('media', $adapter, $this->root, new StoreBaseUrlGenerator('media'));
            MountRegistry::register($mount);
            return $mount;
        };
        $this->writePng = function (Mount $mount, string $key): void {
            $png = Maho::getImageManager()->createImage(80, 80)->fill('00ff00')
                ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
            $mount->write($key, $png);
        };
    });

    afterEach(function (): void {
        Mage::app()->cleanCache([Mage_ConfigurableSwatches_Helper_Productimg::CACHE_TAG]);
        MountRegistry::reset();

        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('resizes a product image and a fallback swatch into the swatch cache on the mount', function (): void {
        $mount = ($this->useMount)(new LocalFilesystemAdapter($this->root));
        ($this->writePng)($mount, 'catalog/product/g/r/green.png');
        ($this->writePng)($mount, 'wysiwyg/swatches/green.png');

        $product = $this->helper->resize('/g/r/green.png', 'product');
        $fallback = $this->helper->resize('green.png', 'media');

        expect($product)->toBe($this->prefix . 'product/g/r/green.png')
            ->and(getimagesizefromstring($mount->read($product))[0])->toBe(20)
            ->and($fallback)->toBe($this->prefix . 'media/green.png')
            ->and($mount->fileExists($fallback))->toBeTrue();
    });

    it('returns false for a missing source and for a name that leaves its directory', function (string $filename): void {
        $mount = ($this->useMount)(new LocalFilesystemAdapter($this->root));
        $mount->write('secret.png', 'x');

        expect($this->helper->resize($filename, 'media'))->toBeFalse();
    })->with([
        'a missing source' => ['blue.png'],
        'a dot segment' => ['../../../../../../secret.png'],
    ]);

    it('keeps the file checks of a remote mount in the cache', function (): void {
        $adapter = new RemoteFilesystemAdapter(new LocalFilesystemAdapter($this->root));
        $mount = ($this->useMount)($adapter);
        ($this->writePng)($mount, 'wysiwyg/swatches/green.png');

        $this->helper->resize('green.png', 'media');
        $this->helper->resize('blue.png', 'media');
        $calls = $adapter->fileExistsCalls;
        $this->helper->resize('green.png', 'media');
        $this->helper->resize('blue.png', 'media');

        expect($adapter->fileExistsCalls)->toBe($calls);
    });

    it('clears the swatch cache on the mount and the remembered file checks', function (): void {
        $adapter = new RemoteFilesystemAdapter(new LocalFilesystemAdapter($this->root));
        $mount = ($this->useMount)($adapter);
        ($this->writePng)($mount, 'wysiwyg/swatches/green.png');
        $path = $this->helper->resize('green.png', 'media');

        $this->helper->clearSwatchesCache();
        $calls = $adapter->fileExistsCalls;
        $again = $this->helper->resize('green.png', 'media');

        expect($again)->toBe($path)
            ->and($adapter->fileExistsCalls)->toBeGreaterThan($calls)
            ->and($mount->fileExists($path))->toBeTrue();
    });
});

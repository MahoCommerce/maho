<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

/** A local adapter that is not a LocalFilesystemAdapter, so the mount acts as a remote one. */
final class ProductimgMountTestRemoteAdapter implements FilesystemAdapter
{
    public int $fileExistsCalls = 0;

    public function __construct(private readonly LocalFilesystemAdapter $inner) {}

    public function fileExists(string $path): bool
    {
        $this->fileExistsCalls++;
        return $this->inner->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->inner->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->inner->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->inner->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->inner->read($path);
    }

    public function readStream(string $path)
    {
        return $this->inner->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->inner->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->inner->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->inner->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->inner->setVisibility($path, $visibility);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->inner->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->inner->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->inner->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->inner->fileSize($path);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return $this->inner->listContents($path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->inner->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->inner->copy($source, $destination, $config);
    }
}

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
            $mount = new Mount('media', $adapter, $this->root, new StoreUrlGenerator('media'));
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
        $adapter = new ProductimgMountTestRemoteAdapter(new LocalFilesystemAdapter($this->root));
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
        $adapter = new ProductimgMountTestRemoteAdapter(new LocalFilesystemAdapter($this->root));
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

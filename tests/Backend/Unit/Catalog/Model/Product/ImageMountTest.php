<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('product image URLs and the image route on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_product_image_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreUrlGenerator('media'));
        MountRegistry::register($this->mount);

        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());
        $this->extension = Maho::getConfiguredImageExtension();
        $this->variants = Mage::getSingleton('catalog/product_image_variant');
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->connection->beginTransaction();
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Variant::CACHE_ID);

        $this->writePng = function (string $key, int $width, int $height): void {
            $png = Maho::getImageManager()->createImage($width, $height)->fill('ff0000')
                ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
            $this->mount->write($key, $png);
        };
        $this->urlFor = function (?string $file, string $subdir = 'small_image', ?int $size = 120): string {
            $product = Mage::getModel('catalog/product')->setData($subdir, $file);
            $helper = Mage::helper('catalog/image')->init($product, $subdir);
            if ($size !== null) {
                $helper->resize($size);
            }
            return (string) $helper;
        };
        $this->keyOf = fn(string $url): string => substr($url, strlen($this->mount->publicUrl('')));
    });

    afterEach(function (): void {
        $this->connection->rollBack();
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Variant::CACHE_ID);
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

    it('computes the URL from the params, with no source file and no resize', function (): void {
        $url = ($this->urlFor)('/n/o/nofile.jpg');

        $storeId = Mage::app()->getStore()->getId();
        expect(($this->keyOf)($url))->toMatch(
            '#^catalog/product/cache/' . $storeId . '/small_image/120x/[0-9a-f]{32}/n/o/nofile\.jpg' . preg_quote($this->extension, '#') . '$#',
        )->and($this->mount->directoryExists('catalog/product/cache'))->toBeFalse();
    });

    it('rebuilds the rendered image from its cache path, with or without a size', function (?int $size): void {
        $key = ($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg', 'image', $size));

        $image = $this->variants->createImage($key);

        expect($image)->toBeInstanceOf(Mage_Catalog_Model_Product_Image::class)
            ->and($image->getCacheKey())->toBe($key)
            ->and($image->getSourceFile())->toBe('/n/o/nofile.jpg');
    })->with([
        'with a size' => [300],
        'without a size' => [null],
    ]);

    it('creates the resized image on the first request and writes it to the mount', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $key = ($this->keyOf)(($this->urlFor)('/r/e/red.png'));

        $binary = $this->variants->createImage($key)->getCacheBinary();

        expect(getimagesizefromstring($binary)[0])->toBe(120)
            ->and($this->mount->read($key))->toBe($binary);
    });

    it('refuses a cache path that no template rendered', function (): void {
        $key = 'catalog/product/cache/1/small_image/999x/' . str_repeat('a', 32) . '/n/o/nofile.jpg' . $this->extension;

        expect($this->variants->createImage($key))->toBeNull();
    });

    it('refuses a recorded variant with a source outside catalog/product or inside the cache', function (string $file): void {
        $key = ($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg'));
        $variantPath = substr($key, 0, strpos($key, '/n/o/nofile.jpg'));

        expect($this->variants->createImage($variantPath . $file . $this->extension))->toBeNull();
    })->with([
        'a dot segment' => ['/../../../../../../etc/passwd'],
        'the cache' => ['/cache/1/small_image/x.jpg'],
    ]);

    it('refuses a file name without the configured extension', function (): void {
        $key = ($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg'));

        expect($this->variants->createImage(substr($key, 0, -strlen($this->extension)) . '.bad'))->toBeNull();
    });

    it('takes the configured placeholder with no file check when the product has no image', function (): void {
        Mage::app()->getStore()->setConfig('catalog/placeholder/small_image_placeholder', 'default/ph.png');

        $key = ($this->keyOf)(($this->urlFor)(null));

        expect($key)->toEndWith('/placeholder/default/ph.png' . $this->extension);
    });

    it('sends a missing source to the configured placeholder, and to the skin placeholder when that one is missing too', function (): void {
        Mage::app()->getStore()->setConfig('catalog/placeholder/small_image_placeholder', 'default/ph.png');
        ($this->writePng)('catalog/product/placeholder/default/ph.png', 50, 50);
        $image = $this->variants->createImage(($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg')));

        $configured = $image->getPlaceholderUrl();
        $this->mount->delete('catalog/product/placeholder/default/ph.png');
        $skin = $image->getPlaceholderUrl();

        expect($configured)->toEndWith('/placeholder/default/ph.png' . $this->extension)
            ->and($skin)->toEndWith('/images/catalog/product/placeholder.svg');
    });

    it('deletes the resized copies of a source in every recorded variant', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $small = ($this->keyOf)(($this->urlFor)('/r/e/red.png', 'small_image', 120));
        $large = ($this->keyOf)(($this->urlFor)('/r/e/red.png', 'small_image', 240));
        $this->variants->createImage($small)->getCacheBinary();
        $this->variants->createImage($large)->getCacheBinary();

        $this->variants->deleteCachedCopies('/r/e/red.png');

        expect($this->mount->fileExists($small))->toBeFalse()
            ->and($this->mount->fileExists($large))->toBeFalse()
            ->and($this->mount->fileExists('catalog/product/r/e/red.png'))->toBeTrue();
    });

    it('clears the whole resize cache on the mount', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $key = ($this->keyOf)(($this->urlFor)('/r/e/red.png'));
        $this->variants->createImage($key)->getCacheBinary();

        Mage::getModel('catalog/product_image')->clearCache();

        expect($this->mount->fileExists($key))->toBeFalse()
            ->and($this->mount->fileExists('catalog/product/r/e/red.png'))->toBeTrue();
    });

    it('warms every recorded size of a role image once', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $small = ($this->keyOf)(($this->urlFor)('/o/t/other.png', 'small_image', 120));
        $large = ($this->keyOf)(($this->urlFor)('/o/t/other.png', 'small_image', 240));
        $warmer = new class extends Mage_Catalog_Model_Product_Image_Warmer {
            public function warm(string $file, array $variants): int
            {
                return $this->warmFile($file, $variants);
            }
        };
        $variants = $this->variants->getParamsFor((int) Mage::app()->getStore()->getId(), 'small_image');

        $first = $warmer->warm('/r/e/red.png', $variants);
        $second = $warmer->warm('/r/e/red.png', $variants);

        expect($first)->toBe(2)
            ->and($second)->toBe(0)
            ->and($this->mount->fileExists(str_replace('/o/t/other.png', '/r/e/red.png', $small)))->toBeTrue()
            ->and($this->mount->fileExists(str_replace('/o/t/other.png', '/r/e/red.png', $large)))->toBeTrue();
    });
});

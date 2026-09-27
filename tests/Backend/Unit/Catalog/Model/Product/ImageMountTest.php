<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('product image URLs and the image route on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_product_image_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());
        $this->extension = Maho::getConfiguredImageExtension();
        $this->sizes = Mage::getSingleton('catalog/product_image_size');
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->connection->beginTransaction();
        $this->connection->delete(Mage::getSingleton('core/resource')->getTableName('catalog/product_image_size'));
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Size::CACHE_ID);

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
        $this->galleryProduct = function (string $file): int {
            $resource = Mage::getSingleton('core/resource');
            $productId = (int) $this->connection->fetchOne($this->connection->select()
                ->from($resource->getTableName('catalog/product_website'), 'product_id')
                ->where('website_id = ?', (int) Mage::app()->getStore()->getWebsiteId())
                ->limit(1));
            $this->connection->insert($resource->getTableName(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE), [
                'attribute_id' => (int) Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'media_gallery')->getId(),
                'entity_id' => $productId,
                'value' => $file,
            ]);
            ($this->writePng)('catalog/product' . $file, 400, 200);
            return $productId;
        };
        $this->resizer = new class extends Mage_Catalog_Model_Product_Image_Resizer {
            public function resize(string $file, array $sizes): int
            {
                return $this->resizeFile($file, $sizes);
            }
        };
    });

    afterEach(function (): void {
        $this->connection->rollBack();
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Size::CACHE_ID);
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

        $image = $this->sizes->createImage($key);

        expect($image)->toBeInstanceOf(Mage_Catalog_Model_Product_Image::class)
            ->and($image->getResizedStoragePath())->toBe($key)
            ->and($image->getSourceFile())->toBe('/n/o/nofile.jpg');
    })->with([
        'with a size' => [300],
        'without a size' => [null],
    ]);

    it('creates the resized image on the first request and writes it to the mount', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $key = ($this->keyOf)(($this->urlFor)('/r/e/red.png'));

        $binary = $this->sizes->createImage($key)->getResizedBinary();

        expect(getimagesizefromstring($binary)[0])->toBe(120)
            ->and($this->mount->read($key))->toBe($binary);
    });

    it('gives the image attribute frontend a resize URL for a size, and the original URL without one', function (): void {
        $product = Mage::getModel('catalog/product')->setData('small_image', '/n/o/nofile.jpg');
        $frontend = Mage::getSingleton('eav/config')->getAttribute('catalog_product', 'small_image')->getFrontend();

        $resized = ($this->keyOf)($frontend->getUrl($product, '120x90'));
        $original = $frontend->getUrl($product);

        expect($resized)->toMatch('#^catalog/product/cache/\d+/small_image/120x90/[0-9a-f]{32}/n/o/nofile\.jpg#')
            ->and($original)->toEndWith('/catalog/product/n/o/nofile.jpg');
    });

    it('updates the last render date once a day and forgets the sizes that no template rendered', function (): void {
        $table = Mage::getSingleton('core/resource')->getTableName('catalog/product_image_size');
        $key = ($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg'));
        $path = substr($key, strlen('catalog/product/cache/'), strpos($key, '/n/o/nofile.jpg') - strlen('catalog/product/cache/'));
        $this->connection->update($table, ['last_seen_at' => '2020-01-01 00:00:00', 'created_at' => '2020-01-01 00:00:00']);
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Size::CACHE_ID);
        Mage::unregister('_singleton/catalog/product_image_size');
        $old = ($this->keyOf)(($this->urlFor)('/o/l/old.jpg', 'thumbnail', 50));
        $this->connection->update($table, ['last_seen_at' => '2020-01-01 00:00:00'], ['path LIKE ?' => '%/thumbnail/%']);
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Size::CACHE_ID);
        Mage::unregister('_singleton/catalog/product_image_size');

        ($this->urlFor)('/n/o/nofile.jpg');
        $seen = $this->connection->fetchOne($this->connection->select()->from($table, 'last_seen_at')->where('path = ?', $path));
        $pruned = Mage::getSingleton('catalog/product_image_size')->prune(30);

        expect(substr((string) $seen, 0, 10))->toBe(Mage_Core_Model_Locale::todayUtc())
            ->and($pruned)->toBe(1)
            ->and(Mage::getSingleton('catalog/product_image_size')->createImage($key))->not->toBeNull()
            ->and(Mage::getSingleton('catalog/product_image_size')->createImage($old))->toBeNull();
    });

    it('refuses a cache path that no template rendered', function (): void {
        $key = 'catalog/product/cache/1/small_image/999x/' . str_repeat('a', 32) . '/n/o/nofile.jpg' . $this->extension;

        expect($this->sizes->createImage($key))->toBeNull();
    });

    it('refuses a recorded size with a source outside catalog/product or inside the cache', function (string $file): void {
        $key = ($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg'));
        $sizePath = substr($key, 0, strpos($key, '/n/o/nofile.jpg'));

        expect($this->sizes->createImage($sizePath . $file . $this->extension))->toBeNull();
    })->with([
        'a dot segment' => ['/../../../../../../etc/passwd'],
        'the cache' => ['/cache/1/small_image/x.jpg'],
    ]);

    it('refuses a file name without the configured extension', function (): void {
        $key = ($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg'));

        expect($this->sizes->createImage(substr($key, 0, -strlen($this->extension)) . '.bad'))->toBeNull();
    });

    it('takes the configured placeholder with no file check when the product has no image', function (): void {
        Mage::app()->getStore()->setConfig('catalog/placeholder/small_image_placeholder', 'default/ph.png');

        $key = ($this->keyOf)(($this->urlFor)(null));

        expect($key)->toEndWith('/placeholder/default/ph.png' . $this->extension);
    });

    it('sends a missing source to the configured placeholder, and to the skin placeholder when that one is missing too', function (): void {
        Mage::app()->getStore()->setConfig('catalog/placeholder/small_image_placeholder', 'default/ph.png');
        ($this->writePng)('catalog/product/placeholder/default/ph.png', 50, 50);
        $image = $this->sizes->createImage(($this->keyOf)(($this->urlFor)('/n/o/nofile.jpg')));

        $configured = $image->getPlaceholderUrl();
        $this->mount->delete('catalog/product/placeholder/default/ph.png');
        $skin = $image->getPlaceholderUrl();

        expect($configured)->toEndWith('/placeholder/default/ph.png' . $this->extension)
            ->and($skin)->toEndWith('/images/catalog/product/placeholder.svg');
    });

    it('deletes the resized copies of a source in every recorded size', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $small = ($this->keyOf)(($this->urlFor)('/r/e/red.png', 'small_image', 120));
        $large = ($this->keyOf)(($this->urlFor)('/r/e/red.png', 'small_image', 240));
        $this->sizes->createImage($small)->getResizedBinary();
        $this->sizes->createImage($large)->getResizedBinary();

        $this->sizes->deleteCachedCopies('/r/e/red.png');

        expect($this->mount->fileExists($small))->toBeFalse()
            ->and($this->mount->fileExists($large))->toBeFalse()
            ->and($this->mount->fileExists('catalog/product/r/e/red.png'))->toBeTrue();
    });

    it('clears the whole resize cache on the mount', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $key = ($this->keyOf)(($this->urlFor)('/r/e/red.png'));
        $this->sizes->createImage($key)->getResizedBinary();

        Mage::getModel('catalog/product_image')->clearCache();

        expect($this->mount->fileExists($key))->toBeFalse()
            ->and($this->mount->fileExists('catalog/product/r/e/red.png'))->toBeTrue();
    });

    it('resizes every recorded size of a role image once', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $small = ($this->keyOf)(($this->urlFor)('/o/t/other.png', 'small_image', 120));
        $large = ($this->keyOf)(($this->urlFor)('/o/t/other.png', 'small_image', 240));
        $resizer = new class extends Mage_Catalog_Model_Product_Image_Resizer {
            public function resize(string $file, array $sizes): int
            {
                return $this->resizeFile($file, $sizes);
            }
        };
        $sizes = $this->sizes->getParamsFor((int) Mage::app()->getStore()->getId(), 'small_image');

        $first = $resizer->resize('/r/e/red.png', $sizes);
        $second = $resizer->resize('/r/e/red.png', $sizes);

        expect($first)->toBe(2)
            ->and($second)->toBe(0)
            ->and($this->mount->fileExists(str_replace('/o/t/other.png', '/r/e/red.png', $small)))->toBeTrue()
            ->and($this->mount->fileExists(str_replace('/o/t/other.png', '/r/e/red.png', $large)))->toBeTrue();
    });

    it('resizes the gallery images of a product with the sizes of the gallery roles', function (): void {
        $productId = ($this->galleryProduct)('/g/a/gallery.png');
        $thumbnail = ($this->keyOf)(($this->urlFor)('/o/t/other.png', 'thumbnail', 75));

        $count = Mage::getModel('catalog/product_image_resizer')->resizeProducts([$productId]);

        expect($productId)->toBeGreaterThan(0)
            ->and($count)->toBe(1)
            ->and($this->mount->fileExists(str_replace('/o/t/other.png', '/g/a/gallery.png', $thumbnail)))->toBeTrue();
    });

    it('reads the recorded sizes again for each resize, as a long queue worker needs', function (): void {
        $productId = ($this->galleryProduct)('/g/a/late.png');
        $stale = Mage::getModel('catalog/product_image_size');
        $stale->getParamsFor((int) Mage::app()->getStore()->getId(), 'thumbnail');
        ($this->urlFor)('/o/t/other.png', 'thumbnail', 75);
        Mage::unregister('_singleton/catalog/product_image_size');
        Mage::register('_singleton/catalog/product_image_size', $stale);

        $count = Mage::getModel('catalog/product_image_resizer')->resizeProducts([$productId]);

        expect($count)->toBe(1);
    });

    it('logs a read error of a remote source and does not stop the resize', function (): void {
        ($this->writePng)('catalog/product/r/e/red.png', 400, 200);
        $adapter = new class ($this->root) extends LocalFilesystemAdapter {
            #[\Override]
            public function read(string $path): string
            {
                throw \League\Flysystem\UnableToReadFile::fromLocation($path, 'refused');
            }
        };
        MountRegistry::register(new Mount('media', $adapter, null, new StoreBaseUrlGenerator('media')));
        ($this->urlFor)('/o/t/other.png', 'small_image', 120);
        $sizes = $this->sizes->getParamsFor((int) Mage::app()->getStore()->getId(), 'small_image');

        expect(fn() => $this->resizer->resize('/r/e/red.png', $sizes))->not->toThrow(\Throwable::class)
            ->and($this->resizer->resize('/r/e/red.png', $sizes))->toBe(0);
    });
});

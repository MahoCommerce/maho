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
use Tests\Helpers\RemoteFilesystemAdapter;

uses(Tests\MahoBackendTestCase::class);

describe('resized image files of an image entity', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_image_files_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        Mage::app()->setCurrentStore($this->storeId);
        $this->restore = [
            'design/theme/skin' => Mage::getStoreConfig('design/theme/skin', $this->storeId),
            Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'product' => Mage::getStoreConfig(Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'product', $this->storeId),
        ];
        Mage::getSingleton('core/image_config')->reset();

        $this->writePng = function (string $key, int $width, int $height): void {
            $png = Maho::getImageManager()->createImage($width, $height)->fill('ff0000')
                ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
            $this->mount->write($key, $png);
        };
        $this->sizeOf = function (string $key): array {
            $info = \Maho\Io::getImageSize($this->root . '/' . $key);
            return [$info[0], $info[1]];
        };
        $this->settings = Mage::getSingleton('core/image_config')->getActiveSettings('product', $this->storeId);
        $this->pathOf = fn(int $width, string $file = '/a/b/shirt.png', ?Mage_Core_Model_Image_Settings $settings = null): string
            => Mage_Core_Model_Image_Set::buildPath('catalog/product', 'catalog/product' . $file, $settings ?? $this->settings, $width);

        ($this->writePng)('catalog/product/a/b/shirt.png', 400, 200);
    });

    afterEach(function (): void {
        foreach ($this->restore as $path => $value) {
            Mage::app()->getStore($this->storeId)->setConfig($path, $value);
        }
        Mage::getSingleton('core/image_config')->reset();
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

    it('takes the 1x and 2x widths of the slots of the default theme when no settings are active yet', function (): void {
        expect($this->settings->widths)->toBe([150, 300, 600, 1200, 2400])
            ->and($this->settings->ratio)->toBeNull();
    });

    it('builds the URL of the next larger width and a srcset of every width, with no file read', function (): void {
        $set = Mage::helper('core/image')->init('product', '/a/b/shirt.png');
        $base = $this->mount->publicUrl('');

        expect($set->getUrl(240))->toBe($base . ($this->pathOf)(300))
            ->and($set->getUrl(0))->toBe($base . ($this->pathOf)(2400))
            ->and($set->getPath(240))->toStartWith('catalog/product/cache/' . $this->settings->getHash() . '/300/a/b/shirt.png')
            ->and($set->getSrcset())->toStartWith($base . ($this->pathOf)(150) . ' 150w, ')
            ->and($set->getSrcset())->toEndWith($base . ($this->pathOf)(2400) . ' 2400w')
            ->and($this->mount->fileExists(($this->pathOf)(300)))->toBeFalse();
    });

    it('writes img attributes with the 2x file of the slot as src, the slot width and the ratio of the source', function (): void {
        $set = Mage::helper('core/image')->init('product', '/a/b/shirt.png');
        $base = $this->mount->publicUrl('');

        expect($set->getImgAttributes('listing'))->toBe(
            'src="' . $base . ($this->pathOf)(600) . '" srcset="' . htmlspecialchars($set->getSrcset()) . '"'
            . ' sizes="(max-width: 300px) 100vw, 300px" width="300" height="150"',
        )
            ->and($set->getImgAttributes('thumbnail'))->toEndWith(' sizes="(max-width: 150px) 100vw, 150px" width="150" height="75"');
    });

    it('gives the largest file and 100vw for an unknown slot', function (): void {
        $set = Mage::helper('core/image')->init('product', '/a/b/shirt.png');

        expect($set->getSizes('nothing'))->toBe('100vw')
            ->and($set->getSrcAttributes('nothing'))->toStartWith('src="' . $this->mount->publicUrl('') . ($this->pathOf)(2400) . '"')
            ->and($set->getImgAttributes('nothing'))->toEndWith(' width="2400" height="1200"');
    });

    it('creates one file for each width, and never scales a source up', function (): void {
        $count = Mage::getSingleton('core/image_resizer')->createFiles('product', ['/a/b/shirt.png'], [$this->settings->getHash() => $this->settings]);

        expect($count)->toBe(5)
            ->and(($this->sizeOf)(($this->pathOf)(150)))->toBe([150, 75])
            ->and(($this->sizeOf)(($this->pathOf)(300)))->toBe([300, 150])
            ->and(($this->sizeOf)(($this->pathOf)(600)))->toBe([400, 200])
            ->and(($this->sizeOf)(($this->pathOf)(2400)))->toBe([400, 200]);
    });

    it('frames the source in the box of a ratio with contain, and crops it with cover', function (): void {
        $contain = new Mage_Core_Model_Image_Settings([300], ratio: [1, 1]);
        $cover = new Mage_Core_Model_Image_Settings([100], ratio: [1, 1], fit: 'cover');

        Mage::getSingleton('core/image_resizer')->createFiles('product', ['/a/b/shirt.png'], [$contain->getHash() => $contain, $cover->getHash() => $cover]);

        expect(($this->sizeOf)(($this->pathOf)(300, settings: $contain)))->toBe([300, 300])
            ->and(($this->sizeOf)(($this->pathOf)(100, settings: $cover)))->toBe([100, 100]);
    });

    it('skips the files that a listing found', function (): void {
        $resizer = Mage::getSingleton('core/image_resizer');
        $only = [$this->settings->getHash() => $this->settings];
        $resizer->createFiles('product', ['/a/b/shirt.png'], $only);

        expect($resizer->createFiles('product', ['/a/b/shirt.png'], $only, $resizer->listExistingFiles('catalog/product', $this->settings)))->toBe(0);
    });

    it('deletes the resized files of a source in every settings folder', function (): void {
        $other = new Mage_Core_Model_Image_Settings([150], quality: 50);
        $resizer = Mage::getSingleton('core/image_resizer');
        $resizer->createFiles('product', ['/a/b/shirt.png'], [$this->settings->getHash() => $this->settings, $other->getHash() => $other]);

        $resizer->deleteFiles('product', '/a/b/shirt.png');

        expect($this->mount->fileExists(($this->pathOf)(300)))->toBeFalse()
            ->and($this->mount->fileExists(($this->pathOf)(150, settings: $other)))->toBeFalse()
            ->and($this->mount->fileExists('catalog/product/a/b/shirt.png'))->toBeTrue();
    });

    it('deletes only the settings folders that no store view uses', function (): void {
        $unused = new Mage_Core_Model_Image_Settings([150], quality: 50);
        $resizer = Mage::getSingleton('core/image_resizer');
        $resizer->createFiles('product', ['/a/b/shirt.png'], [$this->settings->getHash() => $this->settings, $unused->getHash() => $unused]);
        $this->mount->write('catalog/product/cache/1/small_image/210x/0123/a/b/shirt.png.webp', 'old release');

        expect($resizer->clean('product'))->toBe(2)
            ->and($this->mount->fileExists(($this->pathOf)(300)))->toBeTrue()
            ->and($this->mount->directoryExists('catalog/product/cache/' . $unused->getHash()))->toBeFalse()
            ->and($this->mount->directoryExists('catalog/product/cache/1'))->toBeFalse();
    });

    it('keeps the active settings until the files of the live settings exist', function (): void {
        $config = Mage::getSingleton('core/image_config');
        $store = Mage::app()->getStore($this->storeId);
        $old = new Mage_Core_Model_Image_Settings([150, 300], quality: 50);
        $store->setConfig(Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'product', json_encode($old->toArray()));
        $config->reset();

        expect($config->getActiveSettings('product', $this->storeId))->toEqual($old)
            ->and($config->getPendingStoreIds('product'))->toContain($this->storeId)
            ->and(Mage::helper('core/image')->init('product', '/a/b/shirt.png')->getPath(300))
            ->toContain('/' . $old->getHash() . '/300/')
            ->and(array_keys($config->getAllSettings('product')))->toContain($old->getHash(), $this->settings->getHash());
    });

    it('takes the slots from the child theme, then the parent theme, then base/default', function (): void {
        $design = Mage::getSingleton('core/design_config');
        $store = Mage::app()->getStore($this->storeId);
        $package = (string) Mage::getStoreConfig('design/package/name', $this->storeId) ?: 'base';
        $design->setNode("frontend/{$package}/image_test_child/parent", "{$package}/image_test_parent");
        $design->setNode("frontend/{$package}/image_test_child/images/product/ratio", '3:4');
        $design->setNode("frontend/{$package}/image_test_child/images/product/fit", 'cover');
        $design->setNode("frontend/{$package}/image_test_parent/images/product/ratio", '1:1');
        $design->setNode("frontend/{$package}/image_test_parent/images/slots/thumbnail", '50');
        $design->setNode("frontend/{$package}/image_test_parent/images/slots/listing", '400');
        $design->setNode("frontend/{$package}/image_test_child/images/slots/listing", '100');
        $store->setConfig('design/theme/skin', 'image_test_child');
        Mage::getSingleton('core/image_config')->reset();

        $settings = Mage::getSingleton('core/image_config')->getLiveSettings('product', $this->storeId);
        $blog = Mage::getSingleton('core/image_config')->getLiveSettings('blog', $this->storeId);

        expect($settings->widths)->toBe([50, 100, 200, 600, 1200, 2400])
            ->and($settings->ratio)->toBe([3, 4])
            ->and($settings->fit)->toBe('cover')
            ->and($blog->ratio)->toBeNull()
            ->and(Mage::getSingleton('core/image_config')->getSlotWidth('thumbnail', $this->storeId))->toBe(50)
            ->and(Mage::getSingleton('core/image_config')->getSlotWidth('listing', $this->storeId))->toBe(100)
            ->and(Mage::getSingleton('core/image_config')->getSlotWidth('banner', $this->storeId))->toBe(1200)
            ->and(Mage::getSingleton('core/image_config')->getSlotWidth('nothing', $this->storeId))->toBeNull();
    });
    it('creates a missing file of the active settings on request', function (): void {
        $resizer = Mage::getSingleton('core/image_resizer');
        $path = ($this->pathOf)(300);

        $binary = $resizer->createFileAtPath($path);

        expect($binary)->toBeString()
            ->and($this->mount->read($path))->toBe($binary)
            ->and(($this->sizeOf)($path))->toBe([300, 150]);
    });

    it('keeps the name of an original that is in the output format, and creates its missing file', function (): void {
        $file = '/a/b/flat' . $this->settings->extension;
        ($this->writePng)('catalog/product' . $file, 400, 200);
        $path = ($this->pathOf)(300, $file);

        expect($path)->toEndWith('/300' . $file)
            ->and(($this->pathOf)(300))->toEndWith('/300/a/b/shirt.png' . $this->settings->extension)
            ->and(Mage::getSingleton('core/image_resizer')->createFileAtPath($path))->toBeString()
            ->and(($this->sizeOf)($path))->toBe([300, 150]);
    });

    it('creates a missing file on a mount that is not local, for a CDN that sends a miss to Maho', function (): void {
        $adapter = new RemoteFilesystemAdapter(new LocalFilesystemAdapter($this->root));
        MountRegistry::register(new Mount('media', $adapter, $this->root, new StoreBaseUrlGenerator('media')));
        $path = ($this->pathOf)(300);

        $binary = Mage::getSingleton('core/image_resizer')->createFileAtPath($path);

        expect($binary)->toBeString()
            ->and($this->mount->read($path))->toBe($binary);
    });

    it('creates no file for an unknown settings folder, a width of no slot or a missing source', function (): void {
        $resizer = Mage::getSingleton('core/image_resizer');
        $hash = $this->settings->getHash();
        $extension = $this->settings->extension;

        expect($resizer->createFileAtPath("catalog/product/cache/0123456789ab/300/a/b/shirt.png{$extension}"))->toBeNull()
            ->and($resizer->createFileAtPath("catalog/product/cache/{$hash}/301/a/b/shirt.png{$extension}"))->toBeNull()
            ->and($resizer->createFileAtPath("catalog/product/cache/{$hash}/300/a/b/missing.png{$extension}"))->toBeNull()
            ->and($resizer->createFileAtPath("catalog/product/cache/{$hash}/300/a/b/shirt.png.gif"))->toBeNull()
            ->and($resizer->createFileAtPath("catalog/product/cache/{$hash}/300/../../../etc/x.png{$extension}"))->toBeNull()
            ->and($resizer->createFileAtPath("customer/cache/{$hash}/300/a/b/shirt.png{$extension}"))->toBeNull()
            ->and($this->mount->directoryExists('catalog/product/cache'))->toBeFalse();
    });

    it('gives an entity only the slots that it lists, with its own width for a slot', function (): void {
        $design = Mage::getSingleton('core/design_config');
        $store = Mage::app()->getStore($this->storeId);
        $package = (string) Mage::getStoreConfig('design/package/name', $this->storeId) ?: 'base';
        $design->setNode("frontend/{$package}/image_test_slots/parent", "{$package}/image_test_slots_parent");
        $design->setNode("frontend/{$package}/image_test_slots/images/slots/hero", '1600');
        $design->setNode("frontend/{$package}/image_test_slots_parent/images/product/slots/thumbnail", '');
        $design->setNode("frontend/{$package}/image_test_slots_parent/images/product/slots/gallery", '800');
        $store->setConfig('design/theme/skin', 'image_test_slots');
        $config = Mage::getSingleton('core/image_config');
        $config->reset();

        expect($config->getLiveSettings('product', $this->storeId)->widths)->toBe([150, 300, 800, 1600])
            ->and($config->getLiveSettings('blog', $this->storeId)->widths)->toBe([150, 300, 600, 1200, 1600, 2400, 3200])
            ->and($config->getSlotWidth('gallery', $this->storeId, 'product'))->toBe(800)
            ->and($config->getSlotWidth('gallery', $this->storeId, 'blog'))->toBe(600)
            ->and($config->getSlotWidth('hero', $this->storeId, 'product'))->toBe(1600)
            ->and($config->getSlotWidth('gallery', $this->storeId))->toBe(600);
    });
});

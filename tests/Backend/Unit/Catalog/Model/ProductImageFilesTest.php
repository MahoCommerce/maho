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

describe('product image URLs and the creation of their files', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_product_image_files_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        Mage::app()->setCurrentStore($this->storeId);
        $this->placeholder = Mage::getStoreConfig('catalog/placeholder/image_placeholder', $this->storeId);
        Mage::getSingleton('core/image_config')->reset();
        $this->hash = Mage::getSingleton('core/image_config')->getActiveSettings('product', $this->storeId)->getHash();

        $png = Maho::getImageManager()->createImage(400, 200)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->mount->write('catalog/product/a/b/shirt.png', $png);

        $this->spy = new class extends Mage_Core_Model_Image_Resizer {
            /** @var list<array{string, list<string>}> */
            public array $calls = [];

            /** @var list<array{string, list<string>}> */
            public array $queued = [];

            #[\Override]
            public function createFilesAfterCommit(string $entity, array $files): void
            {
                $this->calls[] = [$entity, $files];
            }

            #[\Override]
            public function queue(string $entity, array $files): void
            {
                $this->queued[] = [$entity, $files];
            }
        };
    });

    afterEach(function (): void {
        Mage::app()->getStore($this->storeId)->setConfig('catalog/placeholder/image_placeholder', $this->placeholder);
        Mage::unregister('_singleton/core/image_resizer');
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

    it('gives the file of the next larger width for a requested size', function (): void {
        $product = Mage::getModel('catalog/product')->setData('image', '/a/b/shirt.png');

        $url = (string) Mage::helper('catalog/image')->init($product, 'image')->resize(75);
        $srcset = Mage::helper('catalog/image')->init($product, 'image')->getSrcset();

        expect($url)->toEndWith("/catalog/product/cache/{$this->hash}/150/a/b/shirt.png" . Maho::getConfiguredImageExtension())
            ->and($srcset)->toContain(' 150w, ')
            ->and(Mage::helper('catalog/image')->init($product, 'image')->getOriginalSizeArray())->toBe([400, 200]);
    });

    it('writes the img attributes of a slot, and only a src and a square box for the skin placeholder', function (): void {
        $store = Mage::app()->getStore($this->storeId);
        $store->setConfig('catalog/placeholder/image_placeholder', '');
        $product = Mage::getModel('catalog/product')->setData('image', '/a/b/shirt.png');

        expect(Mage::helper('catalog/image')->init($product, 'image')->getImgAttributes('thumbnail'))
            ->toContain("/{$this->hash}/300/a/b/shirt.png")
            ->toEndWith(' sizes="(max-width: 150px) 100vw, 150px" width="150" height="75"')
            ->and(Mage::helper('catalog/image')->init(Mage::getModel('catalog/product'), 'image')->getImgAttributes('thumbnail'))
            ->toMatch('/^src="[^"]*placeholder\.svg" width="150" height="150"$/');
    });

    it('gives the gallery box the ratio of the theme, or of the original when the theme keeps it', function (): void {
        $store = Mage::app()->getStore($this->storeId);
        $path = Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'product';
        $active = Mage::getStoreConfig($path, $this->storeId);
        $product = Mage::getModel('catalog/product')->setData('image', '/a/b/shirt.png');

        $original = Mage::helper('catalog/image')->init($product, 'image')->getAspectRatio('gallery');
        $square = new Mage_Core_Model_Image_Settings([150, 300, 600, 1200, 2400], ratio: [1, 1]);
        $store->setConfig($path, json_encode($square->toArray()));
        Mage::getSingleton('core/image_config')->reset();
        try {
            $fixed = Mage::helper('catalog/image')->init($product, 'image')->getAspectRatio('gallery');
        } finally {
            $store->setConfig($path, $active);
        }

        expect($original)->toBe('600/300')
            ->and($fixed)->toBe('600/600')
            ->and(Mage::helper('catalog/image')->init(Mage::getModel('catalog/product'), 'image')->getAspectRatio('gallery'))->toBe('1/1');
    });

    it('takes the larger side of a requested box', function (): void {
        $product = Mage::getModel('catalog/product')->setData('image', '/a/b/shirt.png');

        expect((string) Mage::helper('catalog/image')->init($product, 'image')->resize(105, 280))->toContain('/300/a/b/shirt.png');
    });

    it('gives the image for the deprecated small_image and thumbnail roles', function (): void {
        $product = Mage::getModel('catalog/product')->setData(['image' => '/a/b/shirt.png', 'image_label' => 'Red shirt']);
        $block = Mage::app()->getLayout()->createBlock('catalog/product_list');

        expect((string) Mage::helper('catalog/image')->init($product, 'small_image')->resize(300))->toContain('/300/a/b/shirt.png')
            ->and((string) Mage::helper('catalog/image')->init($product, 'thumbnail')->resize(300))->toContain('/300/a/b/shirt.png')
            ->and($product->getSmallImage())->toBe('/a/b/shirt.png')
            ->and($product->getThumbnail())->toBe('/a/b/shirt.png')
            ->and($block->getImageLabel($product, 'small_image'))->toBe('Red shirt');
    });

    it('uses the placeholder of the config, and the skin placeholder when the config names none', function (): void {
        $product = Mage::getModel('catalog/product');
        $store = Mage::app()->getStore($this->storeId);

        $store->setConfig('catalog/placeholder/image_placeholder', 'default/ph.png');
        $configured = (string) Mage::helper('catalog/image')->init($product, 'image')->resize(300);
        $store->setConfig('catalog/placeholder/image_placeholder', '');
        $skin = (string) Mage::helper('catalog/image')->init($product, 'image')->resize(300);

        expect($configured)->toContain("/catalog/product/cache/{$this->hash}/300/placeholder/default/ph.png")
            ->and($skin)->toEndWith('images/catalog/product/placeholder.svg');
    });

    it('creates the files of a changed main image after a save, and queues the new gallery images', function (): void {
        Mage::register('_singleton/core/image_resizer', $this->spy);
        $product = Mage::getModel('catalog/product')->setOrigData('image', '/o/l/old.png')->setData([
            'image' => '/a/b/shirt.png',
            'media_gallery' => ['images' => [
                ['file' => '/n/e/new.png', 'new_file' => '/n/e/new_1.png'],
                ['file' => '/g/o/gone.png', 'new_file' => '/g/o/gone.png', 'removed' => 1],
                ['file' => '/k/e/kept.png', 'value_id' => 5],
            ]],
        ]);

        Mage::getSingleton('catalog/observer')->createProductImageFiles(new \Maho\Event\Observer(['event' => new \Maho\Event(['product' => $product])]));

        expect($this->spy->calls)->toBe([['product', ['/a/b/shirt.png']]])
            ->and($this->spy->queued)->toBe([['product', ['/n/e/new_1.png']]]);
    });

    it('creates the files of a new gallery image that becomes the main image after a save', function (): void {
        Mage::register('_singleton/core/image_resizer', $this->spy);
        $product = Mage::getModel('catalog/product')->setOrigData('image', '/n/e/new_1.png')->setData([
            'image' => '/n/e/new_1.png',
            'media_gallery' => ['images' => [
                ['file' => '/n/e/new.png', 'new_file' => '/n/e/new_1.png'],
                ['file' => '/o/t/other.png', 'new_file' => '/o/t/other.png'],
            ]],
        ]);

        Mage::getSingleton('catalog/observer')->createProductImageFiles(new \Maho\Event\Observer(['event' => new \Maho\Event(['product' => $product])]));

        expect($this->spy->calls)->toBe([['product', ['/n/e/new_1.png']]])
            ->and($this->spy->queued)->toBe([['product', ['/o/t/other.png']]]);
    });

    it('queues the files that cannot be written after the commit', function (): void {
        $resizer = new class extends Mage_Core_Model_Image_Resizer {
            /** @var list<array{string, list<string>}> */
            public array $queued = [];

            #[\Override]
            public function createFiles(string $entity, iterable $files, ?array $settingsList = null, ?array $existing = null, ?callable $onFile = null, ?callable $onFailure = null): int
            {
                foreach ($files as $file) {
                    $onFailure !== null && $onFailure($file);
                }
                return 0;
            }

            #[\Override]
            public function queue(string $entity, array $files): void
            {
                $this->queued[] = [$entity, $files];
            }
        };

        $resizer->createFilesAfterCommit('product', ['/a/b/shirt.png']);

        expect($resizer->queued)->toBe([['product', ['/a/b/shirt.png']]]);
    });

    it('creates the files after the commit of an open transaction, and at once without one', function (): void {
        $resizer = Mage::getSingleton('core/image_resizer');
        $resource = Mage::getResourceSingleton('core/config');
        $settings = Mage::getSingleton('core/image_config')->getActiveSettings('product', $this->storeId);
        $path = Mage_Core_Model_Image_Set::buildPath('catalog/product', 'catalog/product/a/b/shirt.png', $settings, 300);

        $resource->beginTransaction();
        try {
            $resizer->createFilesAfterCommit('product', ['/a/b/shirt.png']);
            $beforeCommit = $this->mount->fileExists($path);
        } finally {
            $resource->commit();
        }

        expect($beforeCommit)->toBeFalse()
            ->and($this->mount->fileExists($path))->toBeTrue();
    });
});

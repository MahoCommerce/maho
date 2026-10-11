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
        $this->placeholder = Mage::getStoreConfig('catalog/placeholder/small_image_placeholder', $this->storeId);
        Mage::getSingleton('core/image_config')->reset();
        $this->hash = Mage::getSingleton('core/image_config')->getActiveSettings('product', $this->storeId)->getHash();

        $png = Maho::getImageManager()->createImage(400, 200)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->mount->write('catalog/product/a/b/shirt.png', $png);

        $this->spy = new class extends Mage_Core_Model_Image_Resizer {
            /** @var list<array{string, list<string>}> */
            public array $calls = [];

            #[\Override]
            public function createFilesAfterCommit(string $entity, array $files): void
            {
                $this->calls[] = [$entity, $files];
            }
        };
    });

    afterEach(function (): void {
        Mage::app()->getStore($this->storeId)->setConfig('catalog/placeholder/small_image_placeholder', $this->placeholder);
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
        $product = Mage::getModel('catalog/product')->setData('small_image', '/a/b/shirt.png');

        $url = (string) Mage::helper('catalog/image')->init($product, 'small_image')->resize(75);
        $srcset = Mage::helper('catalog/image')->init($product, 'small_image')->getSrcset();

        expect($url)->toEndWith("/catalog/product/cache/{$this->hash}/150/a/b/shirt.png" . Maho::getConfiguredImageExtension())
            ->and($srcset)->toContain(' 150w, ')
            ->and(Mage::helper('catalog/image')->init($product, 'small_image')->getOriginalSizeArray())->toBe([400, 200]);
    });

    it('writes the src attributes, and only a src and a square box for the skin placeholder', function (): void {
        $store = Mage::app()->getStore($this->storeId);
        $store->setConfig('catalog/placeholder/small_image_placeholder', '');
        $product = Mage::getModel('catalog/product')->setData('small_image', '/a/b/shirt.png');

        expect(Mage::helper('catalog/image')->init($product, 'small_image')->getSrcAttributes('80px'))
            ->toContain("/{$this->hash}/300/a/b/shirt.png")
            ->toContain(' sizes="80px"')
            ->and(Mage::helper('catalog/image')->init(Mage::getModel('catalog/product'), 'small_image')->getSrcAttributes('80px'))
            ->toMatch('/^src="[^"]*placeholder\.svg"$/')
            ->and(Mage::helper('catalog/image')->init(Mage::getModel('catalog/product'), 'small_image')->getImgAttributes('thumbnail'))
            ->toMatch('/^src="[^"]*placeholder\.svg" width="100" height="100"$/');
    });

    it('takes the larger side of a requested box', function (): void {
        $product = Mage::getModel('catalog/product')->setData('thumbnail', '/a/b/shirt.png');

        expect((string) Mage::helper('catalog/image')->init($product, 'thumbnail')->resize(105, 280))->toContain('/300/a/b/shirt.png');
    });

    it('uses the placeholder of the config, and the skin placeholder when the config names none', function (): void {
        $product = Mage::getModel('catalog/product');
        $store = Mage::app()->getStore($this->storeId);

        $store->setConfig('catalog/placeholder/small_image_placeholder', 'default/ph.png');
        $configured = (string) Mage::helper('catalog/image')->init($product, 'small_image')->resize(300);
        $store->setConfig('catalog/placeholder/small_image_placeholder', '');
        $skin = (string) Mage::helper('catalog/image')->init($product, 'small_image')->resize(300);

        expect($configured)->toContain("/catalog/product/cache/{$this->hash}/300/placeholder/default/ph.png")
            ->and($skin)->toEndWith('images/catalog/product/placeholder.svg');
    });

    it('creates the files of the changed role images and of the new gallery images after a save', function (): void {
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

        expect($this->spy->calls)->toBe([['product', ['/a/b/shirt.png', '/n/e/new_1.png']]]);
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

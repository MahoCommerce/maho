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
        $this->hash = Mage::getSingleton('core/image_config')->getSettings('product', $this->storeId)->getHash();

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
        $this->provider = new class extends Mage_Catalog_Model_Product_Image_Provider {
            /** @var list<array<int|string>> */
            public array $queued = [];

            #[\Override]
            public function queue(array $productIds): void
            {
                $this->queued[] = $productIds;
            }
        };
        $this->importEvent = function (string $behavior, bool $expectsIds): \Maho\Event\Observer {
            $adapter = $this->createMock(Mage_ImportExport_Model_Import_Entity_Product::class);
            $adapter->method('getBehavior')->willReturn($behavior);
            if ($expectsIds) {
                $adapter->expects($this->once())->method('getAffectedEntityIds')->with(true)->willReturn([7, 9]);
            } else {
                $adapter->expects($this->never())->method('getAffectedEntityIds');
            }
            return new \Maho\Event\Observer(['event' => new \Maho\Event(['adapter' => $adapter])]);
        };
    });

    afterEach(function (): void {
        Mage::app()->getStore($this->storeId)->setConfig('catalog/placeholder/image_placeholder', $this->placeholder);
        Mage::unregister('_singleton/core/image_resizer');
        Mage::unregister('_singleton/catalog/product_image_provider');
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
        $product = Mage::getModel('catalog/product')->setData('image', '/a/b/shirt.png');

        $original = Mage::helper('catalog/image')->init($product, 'image')->getAspectRatio('gallery');
        $square = new class extends Mage_Core_Model_Image_Config {
            #[\Override]
            public function getSettings(string $entity, int $storeId): Mage_Core_Model_Image_Settings
            {
                return new Mage_Core_Model_Image_Settings([150, 300, 600, 1200, 2400], ratio: [1, 1]);
            }
        };
        $config = Mage::registry('_singleton/core/image_config');
        Mage::unregister('_singleton/core/image_config');
        Mage::register('_singleton/core/image_config', $square);
        try {
            $fixed = Mage::helper('catalog/image')->init($product, 'image')->getAspectRatio('gallery');
        } finally {
            Mage::unregister('_singleton/core/image_config');
            if ($config !== null) {
                Mage::register('_singleton/core/image_config', $config);
            }
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
            ->and(withoutDeprecations(fn() => $product->getSmallImage()))->toBe('/a/b/shirt.png')
            ->and(withoutDeprecations(fn() => $product->getThumbnail()))->toBe('/a/b/shirt.png')
            ->and(withoutDeprecations(fn() => $product->getSmallImageLabel()))->toBe('Red shirt')
            ->and(withoutDeprecations(fn() => $product->getThumbnailLabel()))->toBe('Red shirt')
            ->and($block->getImageLabel($product, 'small_image'))->toBe('Red shirt');
    });

    it('sets the image for the deprecated roles of a gallery image', function (): void {
        $product = Mage::getModel('catalog/product')->setTypeId('simple');
        $product->setAttributeSetId((int) $product->getDefaultAttributeSetId());
        $backend = Mage::getModel('catalog/product_attribute_backend_media');

        $backend->setMediaAttribute($product, ['small_image', 'thumbnail'], '/a/b/shirt.png');
        $fromList = $product->getImage();
        $product->unsetData('image');
        $backend->setMediaAttribute($product, 'thumbnail', '/a/b/shirt.png');

        expect($fromList)->toBe('/a/b/shirt.png')
            ->and($product->getImage())->toBe('/a/b/shirt.png');
    });

    it('reads, filters and sorts by image for the deprecated attributes of a product collection', function (): void {
        $product = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['small_image', 'thumbnail_label'])
            ->addAttributeToSelect('thumbnail', 'left')
            ->addAttributeToFilter('small_image', ['neq' => 'no_selection'])
            ->addAttributeToSort('thumbnail')
            ->setPageSize(1)
            ->getFirstItem();

        expect($product->getId())->not->toBeNull()
            ->and($product->getImage())->toBeString()->not->toBe('no_selection');
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

    it('creates and queues no file after a save that does not change the main image', function (): void {
        Mage::register('_singleton/core/image_resizer', $this->spy);
        $product = Mage::getModel('catalog/product')->setOrigData('image', '/a/b/shirt.png')->setData('image', '/a/b/shirt.png');

        Mage::getSingleton('catalog/observer')->createProductImageFiles(new \Maho\Event\Observer(['event' => new \Maho\Event(['product' => $product])]));

        expect($this->spy->calls)->toBe([])
            ->and(array_merge([], ...array_column($this->spy->queued, 1)))->toBe([]);
    });

    it('creates the files of the main image of a duplicate, and queues its other stored images', function (): void {
        $resource = Mage::getSingleton('core/resource');
        $connection = $resource->getConnection('core_write');
        $eavConfig = Mage::getSingleton('eav/config');
        $entityTable = $resource->getTableName('catalog/product');

        $connection->beginTransaction();
        try {
            $connection->insert($entityTable, [
                'entity_type_id' => (int) $eavConfig->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId(),
                'attribute_set_id' => (int) Mage::getModel('catalog/product')->getDefaultAttributeSetId(),
                'type_id' => Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
                'sku' => 'image-files-duplicate-' . uniqid(),
            ]);
            $productId = (int) $connection->lastInsertId($entityTable);
            foreach (['/a/b/shirt.png', '/g/a/gallery.png'] as $file) {
                $connection->insert($resource->getTableName(Mage_Catalog_Model_Resource_Product_Attribute_Backend_Media::GALLERY_TABLE), [
                    'attribute_id' => (int) $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'media_gallery')->getId(),
                    'entity_id' => $productId,
                    'value' => $file,
                ]);
            }
            $connection->insert($resource->getTableName(['catalog/product', 'varchar']), [
                'entity_type_id' => (int) $eavConfig->getEntityType(Mage_Catalog_Model_Product::ENTITY)->getId(),
                'attribute_id' => (int) $eavConfig->getAttribute(Mage_Catalog_Model_Product::ENTITY, 'image')->getId(),
                'store_id' => 0,
                'entity_id' => $productId,
                'value' => '/a/b/shirt.png',
            ]);

            Mage::register('_singleton/core/image_resizer', $this->spy);
            $product = Mage::getModel('catalog/product')->setId($productId)->setIsDuplicate()->setData('image', '/a/b/shirt.png');

            Mage::getSingleton('catalog/observer')->createProductImageFiles(new \Maho\Event\Observer(['event' => new \Maho\Event(['product' => $product])]));
        } finally {
            $connection->rollBack();
        }

        expect($this->spy->calls)->toBe([['product', ['/a/b/shirt.png']]])
            ->and($this->spy->queued)->toBe([['product', ['/g/a/gallery.png']]]);
    });

    it('queues the images of the products of an import', function (): void {
        Mage::register('_singleton/catalog/product_image_provider', $this->provider);

        Mage::getSingleton('catalog/observer')->queueImportedProductImageResize(($this->importEvent)(Mage_ImportExport_Model_Import::BEHAVIOR_APPEND, true));

        expect($this->provider->queued)->toBe([[7, 9]]);
    });

    it('queues nothing for an import that deletes products', function (): void {
        Mage::register('_singleton/catalog/product_image_provider', $this->provider);

        Mage::getSingleton('catalog/observer')->queueImportedProductImageResize(($this->importEvent)(Mage_ImportExport_Model_Import::BEHAVIOR_DELETE, false));

        expect($this->provider->queued)->toBe([]);
    });

    it('creates the files of a category image that a save changes by name', function (): void {
        Mage::register('_singleton/core/image_resizer', $this->spy);
        $backend = Mage::getResourceSingleton('catalog/category')->getAttribute('image')->getBackend();

        $backend->afterSave(Mage::getModel('catalog/category')->setOrigData('image', 'old.png')->setData('image', 'new.png'));
        $backend->afterSave(Mage::getModel('catalog/category')->setOrigData('image', 'same.png')->setData('image', 'same.png'));

        expect($backend)->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_Backend_Image::class)
            ->and($backend->getSource())->toBe('catalog/category')
            ->and($this->spy->calls)->toBe([['category', ['new.png']]]);
    });

    it('creates the files of a blog post image that a save changes by name', function (): void {
        Mage::register('_singleton/core/image_resizer', $this->spy);
        $backend = Mage::getResourceSingleton('blog/post')->getAttribute('image')->getBackend();

        $backend->afterSave(Mage::getModel('blog/post')->setOrigData('image', 'old.png')->setData('image', 'new.png'));
        $backend->afterSave(Mage::getModel('blog/post')->setOrigData('image', 'same.png')->setData('image', 'same.png'));

        expect($backend)->toBeInstanceOf(Mage_Eav_Model_Entity_Attribute_Backend_Image::class)
            ->and($backend->getSource())->toBe('blog')
            ->and($this->spy->calls)->toBe([['blog', ['new.png']]]);
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
        $settings = Mage::getSingleton('core/image_config')->getSettings('product', $this->storeId);
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

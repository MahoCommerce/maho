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

describe('Mage_Catalog_Model_Product_Attribute_Media_Api::update() on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_media_api_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);

        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->connection->beginTransaction();

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
            $png = Maho::getImageManager()->createImage(40, 20)->fill('ff0000')
                ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
            $this->mount->write('catalog/product' . $file, $png);
            return $productId;
        };
    });

    afterEach(function (): void {
        $this->connection->rollBack();
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

    it('refuses content that is not an image and keeps the stored file', function (): void {
        $productId = ($this->galleryProduct)('/t/e/test.png');
        $original = $this->mount->read('catalog/product/t/e/test.png');
        $data = ['file' => ['mime' => 'image/png', 'content' => base64_encode('<?php echo 1;')]];

        expect($productId)->toBeGreaterThan(0)
            ->and(fn() => Mage::getModel('catalog/product_attribute_media_api')->update($productId, '/t/e/test.png', $data))
            ->toThrow(Mage_Api_Exception::class)
            ->and($this->mount->read('catalog/product/t/e/test.png'))->toBe($original);
    });
});

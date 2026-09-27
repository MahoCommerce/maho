<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreUrlGenerator;
use MahoCLI\Commands\CatalogImageClean;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

describe('catalog:image:clean', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_image_clean_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreUrlGenerator('media'));
        MountRegistry::register($this->mount);

        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());
        $this->table = Mage::getSingleton('core/resource')->getTableName('catalog/product_image_variant');
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->connection->beginTransaction();
        $this->connection->delete($this->table);
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Variant::CACHE_ID);
        Mage::unregister('_singleton/catalog/product_image_variant');

        $command = new CatalogImageClean('catalog:image:clean');
        new Application()->addCommand($command);
        $this->tester = new CommandTester($command);
    });

    afterEach(function (): void {
        $this->connection->rollBack();
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Variant::CACHE_ID);
        Mage::unregister('_singleton/catalog/product_image_variant');
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

    it('refuses a number of days below 1', function (): void {
        expect($this->tester->execute(['--days' => '0']))->toBe(Command::INVALID);
    });

    it('forgets a size that no template rendered and deletes its resized files', function (): void {
        $product = Mage::getModel('catalog/product')->setData('small_image', '/o/l/old.jpg');
        (string) Mage::helper('catalog/image')->init($product, 'small_image')->resize(120);
        $path = (string) $this->connection->fetchOne($this->connection->select()->from($this->table, 'path'));
        $this->connection->update($this->table, ['last_seen_at' => '2020-01-01 00:00:00', 'created_at' => '2020-01-01 00:00:00']);
        Mage::app()->removeCache(Mage_Catalog_Model_Product_Image_Variant::CACHE_ID);
        Mage::unregister('_singleton/catalog/product_image_variant');
        $file = Mage_Catalog_Model_Product_Image::CACHE_DIRECTORY . '/' . $path . '/o/l/old.jpg' . Maho::getConfiguredImageExtension();
        $this->mount->write($file, 'resized');

        $status = $this->tester->execute(['--days' => '30']);

        expect($status)->toBe(Command::SUCCESS)
            ->and($this->tester->getDisplay())->toContain('Forgot 1 size(s)')
            ->and($this->mount->fileExists($file))->toBeFalse()
            ->and($this->connection->fetchOne($this->connection->select()->from($this->table, 'COUNT(*)')))->toEqual(0);
    });
});

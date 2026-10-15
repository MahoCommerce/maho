<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\Url\StoreBaseUrlGenerator;
use MahoCLI\Commands\StorageImageResize;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/** A disk adapter that refuses to write below a cache folder, like a bucket that rejects a put. */
final class StorageImageResizeTestCacheFailingAdapter extends LocalFilesystemAdapter
{
    #[\Override]
    public function write(string $path, string $contents, Config $config): void
    {
        if (str_contains($path, '/cache/')) {
            throw UnableToWriteFile::atLocation($path, 'refused');
        }
        parent::write($path, $contents, $config);
    }

    #[\Override]
    public function writeStream(string $path, $contents, Config $config): void
    {
        if (str_contains($path, '/cache/')) {
            throw UnableToWriteFile::atLocation($path, 'refused');
        }
        parent::writeStream($path, $contents, $config);
    }
}

uses(Tests\MahoBackendTestCase::class);

describe('storage:image:resize', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_storage_image_resize_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->useMount = function (LocalFilesystemAdapter $adapter): void {
            MountRegistry::reset();
            $this->mount = new Mount('media', $adapter, $this->root, new StoreBaseUrlGenerator('media'));
            MountRegistry::register($this->mount);
        };
        ($this->useMount)(new LocalFilesystemAdapter($this->root));

        $this->adapter = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->configTable = Mage::getSingleton('core/resource')->getTableName('core/config_data');
        $this->activeRows = $this->adapter->fetchAll($this->adapter->select()
            ->from($this->configTable)
            ->where('path = ?', Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'swatch'));

        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        Mage::app()->setCurrentStore($this->storeId);
        Mage::getSingleton('core/image_config')->reset();
        Mage::getResourceSingleton('core/image_size')->deleteFolder('media', 'wysiwyg/swatches');
        Mage::unregister('_singleton/core/image_size');

        $png = Maho::getImageManager()->createImage(80, 40)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->mount->write('catalog/product/a/b/shirt.png', $png);
        $this->mount->write('wysiwyg/swatches/red.png', $png);

        $command = new StorageImageResize('storage:image:resize');
        new Application()->addCommand($command);
        $this->tester = new CommandTester($command);

        $this->setOldActiveSettings = function (): Mage_Core_Model_Image_Settings {
            $old = new Mage_Core_Model_Image_Settings([36, 72], quality: 50);
            Mage::app()->getStore($this->storeId)->setConfig(Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'swatch', json_encode($old->toArray()));
            Mage::getSingleton('core/image_config')->reset();
            return $old;
        };
    });

    afterEach(function (): void {
        $this->adapter->delete($this->configTable, ['path = ?' => Mage_Core_Model_Image_Config::XML_PATH_ACTIVE . 'swatch']);
        foreach ($this->activeRows as $row) {
            $this->adapter->insert($this->configTable, $row);
        }
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
        Mage::getResourceSingleton('core/image_size')->deleteFolder('media', 'wysiwyg/swatches');
        Mage::unregister('_singleton/core/image_size');
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

    it('refuses an unknown image entity', function (): void {
        expect($this->tester->execute(['--entity' => 'nothing']))->toBe(Command::INVALID);
    });

    it('creates no file with --files-from-stdin when stdin lists every resized file of the image as existing', function (): void {
        $existing = [];
        foreach (Mage::getSingleton('core/image_config')->getAllSettings('product') as $settings) {
            foreach ($settings->widths as $width) {
                $existing[] = Mage_Core_Model_Image_Set::buildPath('catalog/product', 'catalog/product/a/b/shirt.png', $settings, $width);
            }
        }
        $this->tester->setInputs([json_encode(['files' => ['/a/b/shirt.png'], 'existing' => $existing])]);

        $status = $this->tester->execute(['--entity' => 'product', '--files-from-stdin' => true]);

        $lines = explode("\n", trim($this->tester->getDisplay()));
        expect($status)->toBe(Command::SUCCESS)
            ->and(end($lines))->toStartWith('result ')
            ->and(end($lines))->toContain('"created":0')
            ->and($this->mount->directoryExists('catalog/product/cache'))->toBeFalse();
    });

    it('fails with --files-from-stdin when stdin is a plain JSON list', function (): void {
        $this->tester->setInputs([json_encode(['/a/b/shirt.png'])]);

        expect($this->tester->execute(['--entity' => 'product', '--files-from-stdin' => true]))->toBe(Command::FAILURE)
            ->and($this->mount->directoryExists('catalog/product/cache'))->toBeFalse();
    });

    it('fails and keeps the old active settings when the mount refuses to write the resized files', function (): void {
        ($this->useMount)(new StorageImageResizeTestCacheFailingAdapter($this->root));
        $old = ($this->setOldActiveSettings)();
        $config = Mage::getSingleton('core/image_config');

        $status = $this->tester->execute(['--entity' => 'swatch', '--jobs' => 1]);

        expect($status)->toBe(Command::FAILURE)
            ->and($config->getActiveSettings('swatch', $this->storeId))->toEqual($old)
            ->and($config->getPendingStoreIds('swatch'))->toContain($this->storeId)
            ->and($this->mount->directoryExists('wysiwyg/swatches/cache'))->toBeFalse();
    });

    it('creates the resized files and makes the live settings active when every write succeeds', function (): void {
        $old = ($this->setOldActiveSettings)();

        $status = $this->tester->execute(['--entity' => 'swatch', '--jobs' => 1]);

        $config = Mage::getSingleton('core/image_config');
        $live = $config->getLiveSettings('swatch', $this->storeId);
        expect($status)->toBe(Command::SUCCESS)
            ->and($config->getActiveSettings('swatch', $this->storeId))->toEqual($live)
            ->and($config->getActiveSettings('swatch', $this->storeId))->not->toEqual($old)
            ->and($config->getPendingStoreIds('swatch'))->toBe([])
            ->and($this->mount->fileExists(Mage_Core_Model_Image_Set::buildPath('wysiwyg/swatches', 'wysiwyg/swatches/red.png', $live, 72)))->toBeTrue();
    });
});

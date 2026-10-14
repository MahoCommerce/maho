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

describe('the stored sizes of the images', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_image_size_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($this->mount);
        Mage::unregister('_singleton/core/image_size');

        $this->folder = 'catalog/product/size_test_' . uniqid();
        $this->path = $this->folder . '/shirt.png';
        $this->png = fn(int $width, int $height): string => Maho::getImageManager()->createImage($width, $height)->fill('ff0000')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->mount->write($this->path, ($this->png)(400, 200));

        $this->resource = Mage::getResourceSingleton('core/image_size');
        $this->rowOf = fn(string $path, string $mount = 'media'): ?array
            => $this->resource->fetchSize(Mage_Core_Model_Image_Size::getHash($mount, $path));
        // A new request, which knows no size yet
        $this->sizes = function (): Mage_Core_Model_Image_Size {
            Mage::unregister('_singleton/core/image_size');
            return Mage::getSingleton('core/image_size');
        };
    });

    afterEach(function (): void {
        foreach (['media', 'customer'] as $mount) {
            $this->resource->deleteFolder($mount, $this->folder);
            $this->resource->deleteFolder($mount, 'catalog/product/placeholder/' . basename($this->folder));
        }
        queueAdapter()->delete(\Maho\Queue\QueueManager::tableName(), ['dedupe_key LIKE ?' => 'image_size_%']);
        Mage::unregister('_singleton/core/image_size');
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

    it('stores the size of a source when the resizer decodes it', function (): void {
        $settings = Mage::getSingleton('core/image_config')->getActiveSettings('product', (int) Mage::app()->getDefaultStoreView()->getId());
        $file = substr($this->path, strlen('catalog/product'));
        Mage::getSingleton('core/image_resizer')->createFileAtPath(Mage_Core_Model_Image_Set::buildPath('catalog/product', $this->path, $settings, 300));

        expect(($this->rowOf)($this->path))->toBe([400, 200])
            ->and(Mage::helper('core/image')->init('product', $file)->getOriginalSize())->toBe([400, 200]);
    });

    it('reads a file on a local disk once and stores its size', function (): void {
        $first = ($this->sizes)()->get($this->mount, $this->path);
        unlink($this->root . '/' . $this->path);

        expect($first)->toBe([400, 200])
            ->and(($this->sizes)()->get($this->mount, $this->path))->toBe([400, 200]);
    });

    it('never reads a file on a bucket, and queues the read of a missing size', function (): void {
        $remote = new Mount('media', new RemoteFilesystemAdapter(new LocalFilesystemAdapter($this->root)), null, new StoreBaseUrlGenerator('media'));
        MountRegistry::register($remote);
        $file = substr($this->path, strlen('catalog/product'));
        $hash = Mage_Core_Model_Image_Size::getHash('media', $this->path);

        $html = Mage::helper('core/image')->init('product', $file)->getImgAttributes('listing');
        $queued = queueAdapter()->fetchOne(queueAdapter()->select()
            ->from(\Maho\Queue\QueueManager::tableName(), [new \Maho\Db\Expr('COUNT(*)')])
            ->where('dedupe_key = ?', 'image_size_' . $hash));

        expect($html)->not->toContain('height=')
            ->and((int) $queued)->toBe(1)
            ->and(($this->rowOf)($this->path))->toBeNull();

        Mage::getSingleton('core/image_size')->readAll('media', [$this->path]);

        expect(($this->sizes)()->get($remote, $this->path))->toBe([400, 200])
            ->and(Mage::helper('core/image')->init('product', $file)->getImgAttributes('listing'))->toContain('height="150"');
    });

    it('reads the sizes of a listing with one query, and keeps them for the request', function (): void {
        $sizes = ($this->sizes)();
        $sizes->save('media', $this->path, 40, 30);
        $sizes = ($this->sizes)();

        $sizes->preload('product', [substr($this->path, strlen('catalog/product')), null, '', '/../../etc/passwd']);
        $this->resource->deleteHashes([Mage_Core_Model_Image_Size::getHash('media', $this->path)]);

        expect($sizes->get($this->mount, $this->path))->toBe([40, 30]);
    });

    it('stores 0 x 0 for a missing file, so a page does not ask for it again', function (): void {
        $missing = $this->folder . '/missing.png';

        expect(($this->sizes)()->get($this->mount, $missing))->toBeNull()
            ->and(($this->rowOf)($missing))->toBe([0, 0]);
    });

    it('deletes the size of a file that the mount writes, moves, copies or deletes', function (): void {
        $sizes = Mage::getSingleton('core/image_size');
        $other = $this->folder . '/other.png';

        $sizes->save('media', $this->path, 1, 1);
        $this->mount->write($this->path, ($this->png)(100, 50));
        $afterWrite = ($this->rowOf)($this->path);

        $sizes->save('media', $this->path, 1, 1);
        $this->mount->copy($this->path, $other);
        $afterCopy = [($this->rowOf)($this->path), ($this->rowOf)($other)];

        $sizes->save('media', $other, 1, 1);
        $this->mount->move($other, $this->folder . '/moved.png');
        $afterMove = ($this->rowOf)($other);

        $sizes->save('media', $this->path, 1, 1);
        $this->mount->delete($this->path);

        expect($afterWrite)->toBeNull()
            ->and($sizes->get($this->mount, $this->folder . '/moved.png'))->toBe([100, 50])
            ->and($afterCopy)->toBe([[1, 1], null])
            ->and($afterMove)->toBeNull()
            ->and(($this->rowOf)($this->path))->toBeNull();
    });

    it('deletes the sizes of the files in a deleted folder, and only those', function (): void {
        $sizes = Mage::getSingleton('core/image_size');
        $sibling = $this->folder . '_b/shirt.png';
        $sizes->save('media', $this->path, 1, 1);
        $sizes->save('media', $sibling, 2, 2);

        $this->mount->deleteDirectory($this->folder);

        expect(($this->rowOf)($this->path))->toBeNull()
            ->and(($this->rowOf)($sibling))->toBe([2, 2]);
        $this->resource->deleteFolder('media', $this->folder . '_b');
    });

    it('keeps the sizes of the files that storage:migrate copies', function (): void {
        Mage::getSingleton('core/image_size')->save('media', $this->path, 1, 1);

        $this->mount->withoutChangeEvents()->write($this->path, ($this->png)(100, 50));

        expect(($this->rowOf)($this->path))->toBe([1, 1]);
    });

    it('keeps the sizes after a cache flush', function (): void {
        ($this->sizes)()->get($this->mount, $this->path);
        unlink($this->root . '/' . $this->path);

        Mage::app()->cleanCache();

        expect(($this->sizes)()->get($this->mount, $this->path))->toBe([400, 200]);
    });

    it('gives the same path on two mounts two rows', function (): void {
        $sizes = Mage::getSingleton('core/image_size');
        $sizes->save('media', $this->path, 1, 1);
        $sizes->save('customer', $this->path, 2, 2);

        expect(($this->rowOf)($this->path))->toBe([1, 1])
            ->and(($this->rowOf)($this->path, 'customer'))->toBe([2, 2]);
    });

    it('deletes the sizes of the images that no record uses, and keeps placeholders and other mounts', function (): void {
        $sizes = Mage::getSingleton('core/image_size');
        $placeholder = 'catalog/product/placeholder/' . basename($this->folder) . '/image.png';
        $sizes->save('media', $this->path, 1, 1);
        $sizes->save('media', $placeholder, 1, 1);
        $sizes->save('customer', $this->path, 1, 1);

        expect($sizes->deleteOrphans())->toBeGreaterThanOrEqual(1)
            ->and(($this->rowOf)($this->path))->toBeNull()
            ->and(($this->rowOf)($placeholder))->toBe([1, 1])
            ->and(($this->rowOf)($this->path, 'customer'))->toBe([1, 1]);
    });

    it('keeps the sizes while it queues a new read of all of them', function (): void {
        $sizes = Mage::getSingleton('core/image_size');
        $sizes->save('media', $this->path, 1, 1);

        $sizes->refreshAll();

        expect(($this->rowOf)($this->path))->toBe([1, 1]);
    });
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Storage
 */

declare(strict_types=1);

use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\StorageException;
use Maho\Storage\UnknownMountException;

uses(Tests\MahoBackendTestCase::class);

describe(\Maho\Storage\MountRegistry::class, function () {
    it('declares the core mounts on their local directories', function (string $name, string $path): void {
        $mount = Mage::getStorage($name);

        expect($mount)->toBeInstanceOf(Mount::class)
            ->and($mount->name())->toBe($name)
            ->and($mount->isLocal())->toBeTrue()
            ->and($mount->localRoot())->toBe(Mage::getBaseDir() . '/' . $path);
    })->with([
        ['media', 'public/media'],
        ['sitemaps', 'public'],
        ['exports', 'var/export'],
        ['imports', 'var/import'],
        ['feeds', 'var/feedmanager'],
        ['custom_options', 'public/media/custom_options'],
        ['downloadable', 'public/media/downloadable'],
        ['customer', 'public/media/customer'],
        ['customer_address', 'public/media/customer_address'],
    ]);

    it('gives no public url to a mount of private files', function (string $name): void {
        expect(fn() => Mage::getStorage($name)->publicUrl('a/b/file.pdf'))
            ->toThrow(\League\Flysystem\UnableToGeneratePublicUrl::class);
    })->with(['custom_options', 'downloadable', 'customer', 'customer_address']);

    it('returns the same instance twice', function (): void {
        expect(Mage::getStorage('media'))->toBe(Mage::getStorage('media'))
            ->and(MountRegistry::has('media'))->toBeTrue()
            ->and(MountRegistry::names())->toContain('media', 'sitemaps', 'exports', 'imports', 'feeds');
    });

    it('builds media urls from the store media base url', function (): void {
        expect(Mage::getStorage('media')->publicUrl('catalog/product/a.jpg'))
            ->toBe(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'catalog/product/a.jpg');
    });

    it('builds sitemap urls from the store web base url', function (): void {
        expect(Mage::getStorage('sitemaps')->publicUrl('/sitemap.xml'))
            ->toBe(Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . 'sitemap.xml');
    });

    it('rejects an unknown mount and lists the known ones', function (): void {
        expect(fn() => Mage::getStorage('nope'))->toThrow(UnknownMountException::class, 'media');
    });

    it('lets local.xml override a core mount by name', function (): void {
        Mage::getConfig()->setNode('global/storage/mounts/media/adapter/type', 'bogus', true);
        MountRegistry::reset();

        expect(fn() => Mage::getStorage('media'))->toThrow(StorageException::class, 'unknown adapter type "bogus"');
    });

    it('lets local.xml add a public url prefix to a core mount', function (): void {
        Mage::getConfig()->setNode('global/storage/mounts/media/public_url', 'https://cdn.example.com/media/', true);
        MountRegistry::reset();

        expect(Mage::getStorage('media')->publicUrl('catalog/a.jpg'))->toBe('https://cdn.example.com/media/catalog/a.jpg')
            ->and(Mage::getStorage('media')->localRoot())->toBe(Mage::getBaseDir() . '/public/media');
    });

    it('sees a mount declared at runtime after a reset', function (): void {
        Mage::getConfig()->setNode('global/storage/mounts/custom/path', 'var/custom', true);
        MountRegistry::reset();

        expect(Mage::getStorage('custom')->localRoot())->toBe(Mage::getBaseDir() . '/var/custom');
    });

    it('accepts a registered mount for the rest of the request', function (): void {
        $mount = new Mount('media', new League\Flysystem\Local\LocalFilesystemAdapter(sys_get_temp_dir()), sys_get_temp_dir());
        MountRegistry::register($mount);

        expect(Mage::getStorage('media'))->toBe($mount);
    });
});

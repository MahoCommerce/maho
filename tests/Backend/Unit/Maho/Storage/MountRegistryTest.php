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

/**
 * Copies and moves a private file on a local mount that local.xml declares with $nodes.
 *
 * @param array<string, string> $nodes
 * @return array{copy: int, move: int} the mode of the copy and of the moved file
 */
function storageRegistryCopyAndMoveModes(array $nodes): array
{
    $root = sys_get_temp_dir() . '/maho_retain_' . uniqid();
    Mage::getConfig()->setNode('global/storage/mounts/retain/path', $root, true);
    foreach ($nodes as $node => $value) {
        Mage::getConfig()->setNode('global/storage/mounts/retain/' . $node, $value, true);
    }
    MountRegistry::reset();
    $mount = Mage::getStorage('retain')->withoutChangeEvents();

    $umask = umask(0027);
    try {
        $mount->write('a.txt', 'x', ['visibility' => 'private']);
        $mount->write('b.txt', 'x', ['visibility' => 'private']);
        $mount->copy('a.txt', 'copy.txt');
        $mount->move('b.txt', 'moved.txt');
        clearstatcache();

        return ['copy' => fileperms($root . '/copy.txt') & 0777, 'move' => fileperms($root . '/moved.txt') & 0777];
    } finally {
        umask($umask);
        array_map(unlink(...), glob($root . '/*') ?: []);
        rmdir($root);
    }
}

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

    // The source file is private (0600), a public file is 0644, and a plain copy gets 0640 from the umask.
    it('gives a move or copy the visibility of the mount, or of the source when local.xml asks for it', function (array $nodes, int $copy, int $move): void {
        expect(storageRegistryCopyAndMoveModes($nodes))->toBe(['copy' => $copy, 'move' => $move]);
    })->with([
        'neither element' => [[], 0600, 0600],
        'retain_visibility 0' => [['retain_visibility' => '0'], 0640, 0600],
        'visibility public' => [['visibility' => 'public'], 0644, 0644],
        'visibility public, retain_visibility 0' => [['visibility' => 'public', 'retain_visibility' => '0'], 0644, 0644],
        'visibility public, retain_visibility 1' => [['visibility' => 'public', 'retain_visibility' => '1'], 0600, 0600],
    ]);

    it('accepts a registered mount for the rest of the request', function (): void {
        $mount = new Mount('media', new League\Flysystem\Local\LocalFilesystemAdapter(sys_get_temp_dir()), sys_get_temp_dir());
        MountRegistry::register($mount);

        expect(Mage::getStorage('media'))->toBe($mount);
    });
});

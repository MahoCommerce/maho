<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToWriteFile;
use Maho\Storage\Migrator;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

/** A disk adapter that refuses to write one path, like a bucket that rejects a put. */
final class MigratorTestFailingAdapter extends LocalFilesystemAdapter
{
    #[\Override]
    public function writeStream(string $path, $contents, Config $config): void
    {
        if ($path === 'b/broken.jpg') {
            throw UnableToWriteFile::atLocation($path, 'refused');
        }
        parent::writeStream($path, $contents, $config);
    }
}

uses(Tests\MahoBackendTestCase::class);

describe('Maho\Storage\Migrator', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_migrator_' . uniqid();
        mkdir($this->root . '/source', 0777, true);
        mkdir($this->root . '/target', 0777, true);
        $this->source = new Mount('media', new LocalFilesystemAdapter($this->root . '/source'), $this->root . '/source');
        $this->target = new Mount('media', new LocalFilesystemAdapter($this->root . '/target'), $this->root . '/target');
        $this->source->write('a/one.jpg', 'one');
        $this->source->write('a/two.jpg', 'two two');
        $this->source->write('catalog/product/cache/1/x.jpg', 'cache');
    });

    afterEach(function (): void {
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

    it('copies every file that is not excluded', function (): void {
        $result = new Migrator()->migrate($this->source, $this->target, ['catalog/product/cache']);

        expect($result->copied)->toBe(2)
            ->and($result->bytes)->toBe(10)
            ->and($this->target->read('a/two.jpg'))->toBe('two two')
            ->and($this->target->fileExists('catalog/product/cache/1/x.jpg'))->toBeFalse();
    });

    it('continues a run: a file with the same size is skipped and a different one is copied again', function (): void {
        $this->target->write('a/one.jpg', 'one');
        $this->target->write('a/two.jpg', 'half');

        $result = new Migrator()->migrate($this->source, $this->target, ['catalog/product/cache']);

        expect($result->skipped)->toBe(1)
            ->and($result->copied)->toBe(1)
            ->and($this->target->read('a/two.jpg'))->toBe('two two');
    });

    it('copies nothing in a dry run', function (): void {
        $result = new Migrator()->migrate($this->source, $this->target, [], true);

        expect($result->copied)->toBe(3)
            ->and($this->target->listContents('', true)->toArray())->toBe([]);
    });

    it('records a file that fails and copies the others', function (): void {
        $this->source->write('b/broken.jpg', 'x');
        $target = new Mount('media', new MigratorTestFailingAdapter($this->root . '/target'), $this->root . '/target');

        $result = new Migrator()->migrate($this->source, $target, ['catalog/product/cache']);

        expect(array_keys($result->failed))->toBe(['b/broken.jpg'])
            ->and($result->copied)->toBe(2);
    });

    it('gives the local folder of a declared mount as the source', function (): void {
        expect(MountRegistry::getLocalDefault('media')?->localRoot())->toBe(Mage::getBaseDir('media'))
            ->and(fn() => MountRegistry::getLocalDefault('nothing'))->toThrow(\Maho\Storage\UnknownMountException::class);
    });

    it('leaves the folders of the private mounts out of the media copy', function (): void {
        expect(Migrator::foldersOfOtherMounts('media'))
            ->toContain('custom_options', 'downloadable', 'customer', 'customer_address')
            ->and(Migrator::foldersOfOtherMounts('downloadable'))->toBe([]);
    });
});

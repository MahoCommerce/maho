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
use MahoCLI\Commands\StorageMigrate;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

uses(Tests\MahoBackendTestCase::class);

describe('storage:migrate', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_storage_migrate_' . uniqid();
        mkdir($this->root . '/source/downloadable/files', 0777, true);
        mkdir($this->root . '/target', 0777, true);
        file_put_contents($this->root . '/source/a.jpg', 'a');
        file_put_contents($this->root . '/source/downloadable/files/private.zip', 'secret');

        Mage::getConfig()->setNode('global/storage/mounts/media/path', $this->root . '/source', true);
        Mage::getConfig()->setNode('global/storage/mounts/downloadable/path', $this->root . '/source/downloadable', true);
        MountRegistry::reset();
        $this->target = new Mount('media', new LocalFilesystemAdapter($this->root . '/target'), $this->root . '/target');
        MountRegistry::register($this->target);

        $command = new StorageMigrate('storage:migrate');
        new Application()->addCommand($command);
        $this->tester = new CommandTester($command);
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

    it('copies the media folder and leaves the folder of a private mount out', function (): void {
        $status = $this->tester->execute(['mounts' => ['media']]);

        expect($status)->toBe(Command::SUCCESS)
            ->and($this->target->read('a.jpg'))->toBe('a')
            ->and($this->target->fileExists('downloadable/files/private.zip'))->toBeFalse();
    });

    it('does not copy the sitemaps mount, whose local folder is public/', function (): void {
        $status = $this->tester->execute(['mounts' => ['sitemaps']]);

        expect($status)->toBe(Command::SUCCESS)
            ->and($this->tester->getDisplay())->toContain('is not copied')
            ->and($this->target->listContents('', true)->toArray())->toBe([]);
    });
});

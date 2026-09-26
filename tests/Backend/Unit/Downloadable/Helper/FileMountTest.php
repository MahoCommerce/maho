<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Downloadable_Helper_File::moveFileFromTmp() on the downloadable mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_downloadable_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('downloadable', new LocalFilesystemAdapter($this->root), $this->root);
        MountRegistry::register($this->mount);
        $this->helper = Mage::helper('downloadable/file');
    });

    afterEach(function (): void {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($this->root);
    });

    it('moves a new file from the temporary directory and renames it when the name is taken', function (): void {
        $this->mount->write('tmp/links/m/a/manual.pdf', 'new');
        $this->mount->write('files/links/m/a/manual.pdf', 'old');

        $name = $this->helper->moveFileFromTmp(
            Mage_Downloadable_Model_Link::getTmpStoragePath(),
            Mage_Downloadable_Model_Link::getStoragePath(),
            [['file' => '/m/a/manual.pdf.tmp', 'status' => 'new']],
        );

        expect($name)->toBe('/m/a/manual_1.pdf')
            ->and($this->mount->read('files/links/m/a/manual_1.pdf'))->toBe('new')
            ->and($this->mount->fileExists('tmp/links/m/a/manual.pdf'))->toBeFalse();
    });

    it('keeps the name of a file that is not new', function (): void {
        $name = $this->helper->moveFileFromTmp(
            Mage_Downloadable_Model_Link::getTmpStoragePath(),
            Mage_Downloadable_Model_Link::getStoragePath(),
            [['file' => '/m/a/manual.pdf', 'status' => 'old']],
        );

        expect($name)->toBe('/m/a/manual.pdf');
    });

    it('refuses a name that leaves the temporary directory', function (): void {
        $this->mount->write('secret.pdf', 'x');

        expect(fn() => $this->helper->moveFileFromTmp(
            Mage_Downloadable_Model_Link::getTmpStoragePath(),
            Mage_Downloadable_Model_Link::getStoragePath(),
            [['file' => '/../../../secret.pdf', 'status' => 'new']],
        ))->toThrow(Mage_Core_Exception::class);
    });
});

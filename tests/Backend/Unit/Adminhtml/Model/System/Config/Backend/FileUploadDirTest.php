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

describe('the upload directory of a config file field on the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_config_file_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);
        MountRegistry::register($this->mount);

        $this->backend = function (string $uploadDir): Mage_Adminhtml_Model_System_Config_Backend_File {
            $backend = new class extends Mage_Adminhtml_Model_System_Config_Backend_File {
                public string $uploadDir = '';

                #[\Override]
                protected function _getUploadDir()
                {
                    return $this->uploadDir;
                }

                #[\Override]
                public function getOldValue()
                {
                    return 'a.png';
                }

                #[\Override]
                protected function _addWhetherScopeInfo()
                {
                    return false;
                }

                public function deleteFile(): void
                {
                    $this->_afterDelete();
                }
            };
            $backend->uploadDir = $uploadDir;
            return $backend;
        };
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

    it('maps the absolute folder of an old subclass to its path on the media mount', function (): void {
        $this->mount->write('legacy_upload/a.png', 'x');

        ($this->backend)(Mage::getBaseDir('media') . '/legacy_upload')->deleteFile();

        expect($this->mount->fileExists('legacy_upload/a.png'))->toBeFalse();
    });

    it('refuses an absolute folder outside the media folder', function (): void {
        $this->mount->write('etc/a.png', 'x');

        ($this->backend)('/etc')->deleteFile();

        expect($this->mount->fileExists('etc/a.png'))->toBeTrue();
    });
});

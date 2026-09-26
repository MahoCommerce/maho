<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Config;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UrlGeneration\TemporaryUrlGenerator;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

/** A disk adapter that signs URLs like a bucket, and keeps the options of the last URL. */
final class DownloadMountTestSigningAdapter extends LocalFilesystemAdapter implements TemporaryUrlGenerator
{
    public ?Config $lastConfig = null;

    #[\Override]
    public function temporaryUrl(string $path, DateTimeInterface $expiresAt, Config $config): string
    {
        $this->lastConfig = $config;
        return 'https://bucket.example/' . $path . '?expires=' . $expiresAt->getTimestamp();
    }
}

uses(Tests\MahoBackendTestCase::class);

describe('Mage_Downloadable_Helper_Download on the downloadable mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_download_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->useMount = function (LocalFilesystemAdapter $adapter): Mount {
            $mount = new Mount('downloadable', $adapter, $this->root);
            MountRegistry::register($mount);
            return $mount;
        };
        $this->mount = ($this->useMount)(new LocalFilesystemAdapter($this->root));
        $this->mount->write('files/links/m/a/manual.txt', 'the manual');
        $this->helper = new Mage_Downloadable_Helper_Download();
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

    it('streams a file from the mount with its name, size and type', function (): void {
        $this->helper->setResource('files/links/m/a/manual.txt');

        ob_start();
        $this->helper->output();
        $output = ob_get_clean();

        expect($output)->toBe('the manual')
            ->and($this->helper->getFilename())->toBe('manual.txt')
            ->and($this->helper->getFilesize())->toBe(10)
            ->and($this->helper->getContentType())->toStartWith('text/plain')
            ->and($this->helper->getTemporaryUrl())->toBeNull();
    });

    it('takes an absolute path inside the media/downloadable directory, as earlier releases passed', function (): void {
        $this->helper->setResource(Mage::getBaseDir('media') . DS . 'downloadable/files/links/m/a/manual.txt');

        expect($this->helper->getFilesize())->toBe(10);
    });

    it('refuses a path that leaves the mount, and a missing file', function (): void {
        expect(fn() => $this->helper->setResource('../../app/etc/local.xml'))->toThrow(Mage_Core_Exception::class)
            ->and(fn() => $this->helper->setResource('files/links/none.txt')->getFilesize())
            ->toThrow(Mage_Core_Exception::class, 'The file does not exist.');
    });

    it('signs a URL with the file name when the mount supports signed URLs', function (): void {
        $adapter = new DownloadMountTestSigningAdapter($this->root);
        ($this->useMount)($adapter);
        Mage::app()->getStore()->setConfig(Mage_Downloadable_Helper_Download::XML_PATH_CONTENT_DISPOSITION, 'attachment');

        $url = $this->helper->setResource('files/links/m/a/manual.txt')->getTemporaryUrl();

        expect($url)->toStartWith('https://bucket.example/files/links/m/a/manual.txt?expires=')
            ->and($adapter->lastConfig?->get('get_object_options')['ResponseContentDisposition'])
            ->toBe('attachment; filename="manual.txt"');
    });
});

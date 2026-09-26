<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\ApiPlatform\Service\LocalFileUploader;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Maho\Storage\StorageException;
use Maho\Storage\Url\StoreUrlGenerator;

uses(Tests\MahoBackendTestCase::class);

describe('code that reads uploaded files from the media mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_media_readers_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root, new StoreUrlGenerator('media'));
        MountRegistry::register($this->mount);
        Mage::app()->setCurrentStore((int) Mage::app()->getDefaultStoreView()->getId());

        $this->png = Maho::getImageManager()->createImage(30, 10)->fill('0000ff')
            ->encodeUsingFormat(\Intervention\Image\Format::PNG)->toString();
        $this->localFile = function (string $contents): string {
            $path = (string) tempnam(sys_get_temp_dir(), 'maho_media_readers_');
            file_put_contents($path, $contents);
            return $path;
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

    it('copies a local file to the mount, and refuses a file it cannot read', function (): void {
        $source = ($this->localFile)('contents');

        Mount::copyLocalFile($source, $this->mount, 'catalog/category/a.txt');
        unlink($source);

        expect($this->mount->read('catalog/category/a.txt'))->toBe('contents')
            ->and(fn() => Mount::copyLocalFile($source, $this->mount, 'b.txt'))->toThrow(StorageException::class);
    });

    it('stores an API image through saveToStorage() and removes the local file', function (): void {
        $source = ($this->localFile)($this->png);
        $uploader = new LocalFileUploader($source, 'blue.png');
        $uploader->setAllowedExtensions(['png'])->setAllowRenameFiles(true)->setFilesDispersion(false);

        $uploader->saveToStorage($this->mount, 'blog');

        expect($this->mount->read('blog/blue.png'))->toBe($this->png)
            ->and(is_file($source))->toBeFalse();
    });

    it('gives the size of a stored downloadable file, or null', function (?string $file, ?int $size): void {
        MountRegistry::register(new Mount('downloadable', new LocalFilesystemAdapter($this->root), $this->root));
        $this->mount->write('files/links/m/a/manual.pdf', '12345');
        $this->mount->write('secret.pdf', 'secret');

        expect(Mage::helper('downloadable/file')->getStoredFileSize(Mage_Downloadable_Model_Link::getStoragePath(), $file))->toBe($size);
    })->with([
        'a stored file' => ['/m/a/manual.pdf', 5],
        'a missing file' => ['/m/a/other.pdf', null],
        'no file' => [null, null],
        'a dot segment' => ['/../../../secret.pdf', null],
    ]);

    it('reads the size and the URL of a category image from the mount', function (): void {
        $this->mount->write('catalog/category/blue.png', $this->png);
        $category = Mage::getModel('catalog/category')->setImage('blue.png');

        expect($category->getImageSize())->toBe([30, 10])
            ->and($category->getImageUrl())->toBe($this->mount->publicUrl('catalog/category/blue.png'));
    });

    it('refuses a category image name that leaves its directory', function (): void {
        $category = Mage::getModel('catalog/category')->setImage('../../app/etc/local.xml');

        expect($category->getImageStoragePath())->toBeNull()
            ->and($category->getImageUrl())->toBe('')
            ->and($category->getImageSize())->toBeNull();
    });

    it('decodes the image of a media directive from the mount', function (): void {
        $this->mount->write('wysiwyg/blue.png', $this->png);
        $filter = Mage::getModel('cms/adminhtml_template_filter');

        $image = $filter->encodeDirectiveImage('{{media url="wysiwyg/blue.png"}}');

        expect($image->mediaType())->toBe('image/png')
            ->and(fn() => $filter->encodeDirectiveImage('{{media url="../../app/etc/local.xml"}}'))
            ->toThrow(Mage_Core_Exception::class);
    });

    it('takes the email logo from the mount, and the skin logo when the file is missing', function (): void {
        $template = new class extends Mage_Core_Model_Email_Template {
            public function logoUrl(): string
            {
                return $this->_getLogoUrl(Mage::app()->getStore());
            }
        };
        Mage::app()->getStore()->setConfig('design/email/logo', 'default/logo.png');

        $missing = $template->logoUrl();
        $this->mount->write('email/logo/default/logo.png', $this->png);
        $stored = $template->logoUrl();

        expect($missing)->toEndWith('/images/logo_email.gif')
            ->and($stored)->toBe($this->mount->publicUrl('email/logo/default/logo.png'));
    });

    it('embeds the PDF logo from the mount', function (): void {
        Mage::app()->getStore()->setConfig('sales/identity/logo', 'default/logo.png');
        $this->mount->write('sales/store/logo/default/logo.png', $this->png);

        $url = (new Mage_Core_Block_Pdf())->getLogoUrl();

        expect($url)->toBe('data:image/png;base64,' . base64_encode($this->png));
    });
});

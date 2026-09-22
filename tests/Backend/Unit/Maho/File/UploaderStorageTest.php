<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_File
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\File\Uploader;
use Maho\Storage\Mount;

/**
 * A PHP form upload is the only file that passes is_uploaded_file(), so the
 * test uploader writes the temp file it made itself, as the API uploaders do.
 */
class UploaderStorageTestUploader extends Uploader
{
    #[\Override]
    protected function _storeFile(Mount $mount, string $path): bool
    {
        $this->_writeToMount($mount, $path, $this->_file['tmp_name']);
        unlink($this->_file['tmp_name']);
        return true;
    }
}

function uploaderStorageTestRemoveDir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

describe('Maho\File\Uploader::saveToStorage', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_uploader_' . uniqid();
        mkdir($this->root, 0777, true);
        $this->mount = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);
        $this->tmp = tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($this->tmp, 'bytes');
        $_FILES = ['image' => [
            'name' => 'My Photo.png',
            'type' => 'image/png',
            'tmp_name' => $this->tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => 5,
        ]];
    });

    afterEach(function (): void {
        $_FILES = [];
        if (is_file($this->tmp)) {
            unlink($this->tmp);
        }
        uploaderStorageTestRemoveDir($this->root);
    });

    it('writes the cleaned name below the directory and removes the temp file', function (): void {
        $result = new UploaderStorageTestUploader('image')->saveToStorage($this->mount, '/catalog/category/');

        expect($result['path'])->toBe('catalog/category')
            ->and($result['file'])->toBe('My_Photo.png')
            ->and($this->mount->read('catalog/category/My_Photo.png'))->toBe('bytes')
            ->and(is_file($this->tmp))->toBeFalse();
    });

    it('returns the dispersion path in the file name like save()', function (): void {
        $uploader = new UploaderStorageTestUploader('image');
        $uploader->setFilesDispersion(true);
        $result = $uploader->saveToStorage($this->mount, 'catalog/product');

        expect($result['file'])->toBe('/m/y/my_photo.png')
            ->and($uploader->getUploadedFileName())->toBe('/m/y/my_photo.png')
            ->and($this->mount->fileExists('catalog/product/m/y/my_photo.png'))->toBeTrue();
    });

    it('renames the file when the name is taken on the mount', function (): void {
        $this->mount->write('blog/My_Photo.png', 'old');
        $this->mount->write('blog/My_Photo_1.png', 'old');
        $uploader = new UploaderStorageTestUploader('image');
        $uploader->setAllowRenameFiles(true);
        $result = $uploader->saveToStorage($this->mount, 'blog');

        expect($result['file'])->toBe('My_Photo_2.png')
            ->and($this->mount->read('blog/My_Photo_2.png'))->toBe('bytes');
    });

    it('overwrites the file when renaming is off', function (): void {
        $this->mount->write('blog/My_Photo.png', 'old');
        new UploaderStorageTestUploader('image')->saveToStorage($this->mount, 'blog');

        expect($this->mount->read('blog/My_Photo.png'))->toBe('bytes');
    });

    it('uses the new file name and writes to the mount root for an empty directory', function (): void {
        $result = new UploaderStorageTestUploader('image')->saveToStorage($this->mount, '', 'catalog_product.csv');

        expect($result['path'])->toBe('')
            ->and($result['file'])->toBe('catalog_product.csv')
            ->and($this->mount->fileExists('catalog_product.csv'))->toBeTrue();
    });

    it('refuses a temp file that PHP did not receive through a form', function (): void {
        expect(new Uploader('image')->saveToStorage($this->mount, 'blog'))->toBeFalse()
            ->and($this->mount->fileExists('blog/My_Photo.png'))->toBeFalse();
    });

    it('rejects an extension outside the allowed list', function (): void {
        $uploader = new UploaderStorageTestUploader('image');
        $uploader->setAllowedExtensions(['jpg']);

        expect(fn() => $uploader->saveToStorage($this->mount, 'blog'))->toThrow(Exception::class, 'Disallowed file type.');
    });
});

describe('Maho\File\Uploader name helpers', function () {
    it('joins mount path segments with single slashes', function (): void {
        expect(Uploader::joinPath('/catalog/', '', 'product\\a', 'b.png'))->toBe('catalog/product/a/b.png')
            ->and(Uploader::joinPath('', '/'))->toBe('');
    });

    it('finds the first free name on a mount', function (): void {
        $root = sys_get_temp_dir() . '/maho_uploader_' . uniqid();
        mkdir($root, 0777, true);
        $mount = new Mount('media', new LocalFilesystemAdapter($root), $root);
        $mount->write('a/x.png', '1');

        expect(Uploader::getNewFileNameOnMount($mount, 'a/x.png'))->toBe('x_1.png')
            ->and(Uploader::getNewFileNameOnMount($mount, 'a/y.png'))->toBe('y.png');
        uploaderStorageTestRemoveDir($root);
    });
});

describe('Maho\Storage\Mount::pathWithin', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_pathwithin_' . uniqid();
        mkdir($this->root . '/wysiwyg', 0777, true);
        $this->local = new Mount('media', new LocalFilesystemAdapter($this->root), $this->root);
        // A mount without a local root takes the key rule, as a bucket does
        $this->remote = new Mount('media', new LocalFilesystemAdapter($this->root));
    });

    afterEach(function (): void {
        uploaderStorageTestRemoveDir($this->root);
    });

    it('returns the normalized path below the directory on either kind of mount', function (): void {
        foreach ([$this->local, $this->remote] as $mount) {
            expect($mount->pathWithin('wysiwyg', 'sub/a.png'))->toBe('wysiwyg/sub/a.png')
                ->and($mount->pathWithin('/wysiwyg/', '/a.png'))->toBe('wysiwyg/a.png')
                ->and($mount->pathWithin('', 'a.png'))->toBe('a.png')
                ->and($mount->pathWithin('catalog/category', 'x\\y.png'))->toBe('catalog/category/x/y.png');
        }
    });

    it('rejects a name that leaves the directory or names it', function (): void {
        foreach ([$this->local, $this->remote] as $mount) {
            expect($mount->pathWithin('wysiwyg', '../etc/local.xml'))->toBeNull()
                ->and($mount->pathWithin('wysiwyg', 'a/../../b.png'))->toBeNull()
                ->and($mount->pathWithin('wysiwyg', "a\0.png"))->toBeNull()
                ->and($mount->pathWithin('wysiwyg', ''))->toBeNull()
                ->and($mount->pathWithin('wysiwyg', '.'))->toBeNull()
                ->and($mount->pathWithin('wysiwyg', 'a/..'))->toBeNull()
                ->and($mount->pathWithin('', '..'))->toBeNull();
        }
    });

    it('follows the symlink rule of Maho\Io on a local mount', function (): void {
        $outside = sys_get_temp_dir() . '/maho_pathwithin_out_' . uniqid();
        mkdir($outside);
        symlink($outside, $this->root . '/wysiwyg/link');

        expect($this->local->pathWithin('wysiwyg', 'link/a.png'))->toBeNull()
            ->and($this->remote->pathWithin('wysiwyg', 'link/a.png'))->toBe('wysiwyg/link/a.png');
        rmdir($outside);
    });
});

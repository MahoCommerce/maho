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

describe('Mage_Sitemap_Model_Sitemap on the sitemaps mount', function () {
    beforeEach(function (): void {
        $this->root = sys_get_temp_dir() . '/maho_sitemap_' . uniqid();
        mkdir($this->root . '/sitemaps', 0777, true);
        $this->mount = new Mount('sitemaps', new LocalFilesystemAdapter($this->root), $this->root);
        MountRegistry::register($this->mount);
        $this->storeId = (int) Mage::app()->getDefaultStoreView()->getId();
        $this->sitemap = Mage::getModel('sitemap/sitemap')
            ->setSitemapPath('/sitemaps/')
            ->setSitemapFilename('sitemap.xml')
            ->setStoreId($this->storeId);
        $this->connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $this->connection->beginTransaction();
    });

    afterEach(function (): void {
        $this->connection->rollBack();
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

    it('maps the sitemap path and file name to a path on the mount', function (string $sitemapPath, ?string $expected): void {
        $this->sitemap->setSitemapPath($sitemapPath);

        expect($this->sitemap->getStoragePath())->toBe($expected);
    })->with([
        'the root' => ['/', 'sitemap.xml'],
        'a folder' => ['/sitemaps/', 'sitemaps/sitemap.xml'],
        'a dot segment' => ['/../app/etc/', null],
    ]);

    it('puts a urlset file on the mount only when it is closed', function (): void {
        $file = $this->sitemap->openUrlsetFile('sitemap-products.xml');
        $file->write('<url><loc>https://example.com/a</loc></url>');

        $before = $this->mount->fileExists('sitemaps/sitemap-products.xml');
        $file->write('</urlset>');
        $file->close();

        expect($before)->toBeFalse()
            ->and($this->mount->read('sitemaps/sitemap-products.xml'))
            ->toBe(Mage_Sitemap_Model_File::URLSET_HEADER . '<url><loc>https://example.com/a</loc></url></urlset>');
    });

    it('deletes the index file from the mount', function (): void {
        $this->mount->write('sitemaps/sitemap.xml', '<sitemapindex/>');

        $this->sitemap->deleteFile();

        expect($this->mount->fileExists('sitemaps/sitemap.xml'))->toBeFalse();
    });

    it('finds only the files of a sitemap of the store for a request path', function (string $requestPath, ?string $expected): void {
        $this->sitemap->save();

        expect(Mage::helper('sitemap')->getStoredFilePath($requestPath, $this->storeId))->toBe($expected);
    })->with([
        'the index' => ['/sitemaps/sitemap.xml', 'sitemaps/sitemap.xml'],
        'a listed file' => ['/sitemaps/sitemap-products-2.xml', 'sitemaps/sitemap-products-2.xml'],
        'another name' => ['/sitemaps/other.xml', null],
        'another folder' => ['/sitemap.xml', null],
        'not xml' => ['/sitemaps/sitemap-a.txt', null],
    ]);
});

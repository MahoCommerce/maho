<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

uses(Tests\MahoBackendTestCase::class);

/** An observer that reports the file it would stream, because the real one ends the request. */
final class SitemapMountTestObserver extends Mage_Sitemap_Model_Observer
{
    #[\Override]
    protected function sendStoredSitemap(Mage_Core_Controller_Response_Http $response, Mount $mount, string $path): never
    {
        throw new RuntimeException('sent ' . $path);
    }
}

function sitemapMountTestServe(string $uri, array $beforeForwardInfo = []): void
{
    $request = new Mage_Core_Controller_Request_Http(SymfonyRequest::create($uri));
    $request->setBeforeForwardInfo($beforeForwardInfo);
    $action = new Mage_Cms_IndexController($request, new Mage_Core_Controller_Response_Http());

    new SitemapMountTestObserver()->serveStoredSitemap(
        new \Maho\Event\Observer(['event' => new \Maho\Event(['controller_action' => $action])]),
    );
}

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

    it('finds only the files of a stored sitemap for a request path', function (string $requestPath, ?string $expected): void {
        $this->sitemap->save();

        expect(Mage::helper('sitemap')->getStoredFilePath($requestPath))->toBe($expected);
    })->with([
        'the index' => ['/sitemaps/sitemap.xml', 'sitemaps/sitemap.xml'],
        'a listed file' => ['/sitemaps/sitemap-products-2.xml', 'sitemaps/sitemap-products-2.xml'],
        'another name' => ['/sitemaps/other.xml', null],
        'another folder' => ['/sitemap.xml', null],
        'not xml' => ['/sitemaps/sitemap-a.txt', null],
    ]);

    it('finds the sitemap of a store other than the current store', function (): void {
        Mage::app()->setCurrentStore($this->storeId);
        $this->sitemap->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)->save();

        expect(Mage::helper('sitemap')->getStoredFilePath('/sitemaps/sitemap.xml'))->toBe('sitemaps/sitemap.xml');
    });

    it('streams a stored sitemap for a request of its path', function (): void {
        $this->sitemap->save();
        $this->mount->write('sitemaps/sitemap.xml', '<sitemapindex/>');

        expect(fn() => sitemapMountTestServe('/sitemaps/sitemap.xml?from=crawler'))
            ->toThrow(RuntimeException::class, 'sent sitemaps/sitemap.xml');
    });

    it('does not serve a forwarded dispatch, a missing file or another path', function (string $uri, array $beforeForwardInfo): void {
        $this->sitemap->save();
        $this->mount->write('sitemaps/sitemap.xml', '<sitemapindex/>');

        expect(fn() => sitemapMountTestServe($uri, $beforeForwardInfo))->not->toThrow(RuntimeException::class);
    })->with([
        'a forwarded dispatch' => ['/sitemaps/sitemap.xml', ['action_name' => 'noRoute']],
        'a missing file' => ['/sitemaps/sitemap-products-1.xml', []],
    ]);

    it('runs the observer before the session starts, in every area', function (): void {
        $observers = Maho::getCompiledAttributes()['observers']['global']['controller_action_predispatch_session_start'] ?? [];

        expect(array_column($observers, 'method'))->toContain('serveStoredSitemap');
    });

    it('links a sitemap in the grid of a mount with no local disk only when it was generated', function (?string $sitemapTime, bool $fileExists, bool $linked): void {
        $mount = new Mount('sitemaps', new LocalFilesystemAdapter($this->root));
        MountRegistry::register($mount);
        if ($fileExists) {
            $mount->write('sitemaps/sitemap.xml', '<sitemapindex/>');
        }
        $row = new \Maho\DataObject([
            'sitemap_path' => '/sitemaps/',
            'sitemap_filename' => 'sitemap.xml',
            'store_id' => $this->storeId,
            'sitemap_time' => $sitemapTime,
        ]);

        expect(str_contains(new Mage_Adminhtml_Block_Sitemap_Grid_Renderer_Link()->render($row), '<a '))->toBe($linked);
    })->with([
        'generated, no file check' => ['2026-01-01 00:00:00', false, true],
        'saved, not generated' => [null, true, false],
    ]);

    it('writes the index and every file that it lists on the mount', function (): void {
        $this->sitemap->save();
        $this->sitemap->generateXml();

        $index = $this->mount->read('sitemaps/sitemap.xml');
        preg_match_all('#/sitemaps/([^<]+\.xml)</loc>#', $index, $matches);

        expect($index)->toContain('<sitemapindex')
            ->and($matches[1])->not->toBeEmpty();
        foreach ($matches[1] as $name) {
            expect($this->mount->fileExists('sitemaps/' . $name))->toBeTrue();
        }
    });

    it('refuses to save a sitemap path that leaves the local mount', function (): void {
        expect(fn() => $this->sitemap->setSitemapPath('/../app/etc/')->save())
            ->toThrow(Mage_Core_Exception::class, 'Please define correct path');
    });

    it('saves a sitemap in a folder that a mount with no local disk does not hold', function (): void {
        MountRegistry::register(new Mount('sitemaps', new LocalFilesystemAdapter($this->root)));

        expect(fn() => $this->sitemap->setSitemapPath('/missing-folder/')->save())->not->toThrow(Mage_Core_Exception::class);
    });
});

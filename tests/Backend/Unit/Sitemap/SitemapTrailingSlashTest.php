<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Mage_Adminhtml_Model_System_Config_Source_Catalog_Trailingslash as Trailingslash;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;

uses(Tests\MahoBackendTestCase::class);

/** A sitemap that writes one urlset file from the rows that the test gives. */
final class SitemapTrailingSlashTestSitemap extends Mage_Sitemap_Model_Sitemap
{
    public function writePages(array $items, string $baseUrl): void
    {
        $this->writeSingleSitemapFile('pages', $items, $baseUrl, '', '', 0.0, 1, count($items), 100);
    }
}

beforeEach(function (): void {
    $this->root = sys_get_temp_dir() . '/maho_sitemap_slash_' . uniqid();
    mkdir($this->root, 0777, true);
    $this->mount = new Mount('sitemaps', new LocalFilesystemAdapter($this->root), $this->root);
    MountRegistry::register($this->mount);
    $this->store = Mage::app()->getDefaultStoreView();
    $this->adminStore = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    $this->previousConfig = [];
    foreach (['web/url/trailing_slash_behavior', 'web/url/use_store', 'web/unsecure/base_url', 'web/unsecure/base_link_url'] as $path) {
        $this->previousConfig[$path] = $this->store->getConfig($path);
    }
    $this->previousAdminMode = $this->adminStore->getConfig('web/url/trailing_slash_behavior');
    $this->store->setConfig('web/url/use_store', '0');
    $this->store->setConfig('web/unsecure/base_url', 'https://example.com/shop/');
    $this->store->setConfig('web/unsecure/base_link_url', 'https://example.com/shop/');
    (fn() => $this->_baseUrlCache = [])->call($this->store);
});

afterEach(function (): void {
    foreach ($this->previousConfig as $path => $value) {
        $this->store->setConfig($path, $value);
    }
    (fn() => $this->_baseUrlCache = [])->call($this->store);
    $this->adminStore->setConfig('web/url/trailing_slash_behavior', $this->previousAdminMode);
    MountRegistry::reset();
    array_map(unlink(...), glob($this->root . '/*'));
    rmdir($this->root);
});

it('writes each URL in the trailing slash style of the sitemap store, and keeps the base URL', function (string $storeMode, string $adminMode, array $expected): void {
    $this->store->setConfig('web/url/trailing_slash_behavior', $storeMode);
    $this->adminStore->setConfig('web/url/trailing_slash_behavior', $adminMode);
    $items = array_map(fn(string $url) => new \Maho\DataObject(['url' => $url]), ['', 'gear/bags', 'gear/shoes/', 'about.html']);

    new SitemapTrailingSlashTestSitemap()
        ->setSitemapPath('/')
        ->setSitemapFilename('sitemap.xml')
        ->setStoreId((int) $this->store->getId())
        ->writePages($items, $this->store->getBaseUrl());

    preg_match_all('#<loc>([^<]+)</loc>#', $this->mount->read('sitemap-pages.xml'), $matches);
    expect($matches[1])->toBe($expected);
})->with([
    'add' => [Trailingslash::ADD_TRAILING_SLASH, Trailingslash::REMOVE_TRAILING_SLASH, [
        'https://example.com/shop/',
        'https://example.com/shop/gear/bags/',
        'https://example.com/shop/gear/shoes/',
        'https://example.com/shop/about.html',
    ]],
    'remove' => [Trailingslash::REMOVE_TRAILING_SLASH, Trailingslash::ADD_TRAILING_SLASH, [
        'https://example.com/shop/',
        'https://example.com/shop/gear/bags',
        'https://example.com/shop/gear/shoes',
        'https://example.com/shop/about.html',
    ]],
]);

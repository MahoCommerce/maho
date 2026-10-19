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

/** A sitemap that writes only the files of the sitemap_urlset_generating_before listeners and the index. */
final class SitemapTrailingSlashTestSitemap extends Mage_Sitemap_Model_Sitemap
{
    #[\Override]
    protected function generateCategoriesSitemap(int $storeId, string $baseUrl, string $date, int $maxUrlsPerFile): void {}

    #[\Override]
    protected function generateProductsSitemap(int $storeId, string $baseUrl, string $date, int $maxUrlsPerFile): void {}

    #[\Override]
    protected function generatePagesSitemap(int $storeId, string $baseUrl, string $date, int $maxUrlsPerFile): void {}

    #[\Override]
    public function save(): static
    {
        return $this;
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
    foreach (['web/url/trailing_slash_behavior', 'web/url/use_store', 'web/unsecure/base_url', 'web/unsecure/base_link_url', 'blog/general/enable_categories'] as $path) {
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
        ->writeSingleSitemapFile('pages', $items, $this->store->getBaseUrl(), '', '', 0.0, 1, count($items), 100);

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

it('writes the blog URLs with the base URL and the trailing slash style of the sitemap store', function (): void {
    $this->store->setConfig('web/url/trailing_slash_behavior', Trailingslash::REMOVE_TRAILING_SLASH);
    $this->store->setConfig('blog/general/enable_categories', '1');
    $this->adminStore->setConfig('web/url/trailing_slash_behavior', Trailingslash::ADD_TRAILING_SLASH);
    $storeIds = [(int) $this->store->getId()];

    $post = Mage::getModel('blog/post');
    $post->setTitle('Sitemap Slash Post')->setContent('')->setUrlKey('sitemap-slash-post-' . uniqid())->setIsActive(true);
    $post->setStores($storeIds);
    $post->save();
    $category = Mage::getModel('blog/category');
    $category->setName('Sitemap Slash Category')->setUrlKey('sitemap-slash-category-' . uniqid())->setIsActive(true)->setParentId(0);
    $category->setStores($storeIds);
    $category->save();

    $previousStoreId = (int) Mage::app()->getStore()->getId();
    Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);
    try {
        new SitemapTrailingSlashTestSitemap()
            ->setSitemapPath('/')
            ->setSitemapFilename('sitemap.xml')
            ->setStoreId((int) $this->store->getId())
            ->generateXml();
    } finally {
        Mage::app()->setCurrentStore($previousStoreId);
        $post->delete();
        $category->delete();
    }

    preg_match_all('#<loc>([^<]+)</loc>#', $this->mount->read('sitemap-blog.xml'), $matches);
    expect($matches[1])
        ->toContain('https://example.com/shop/blog')
        ->toContain('https://example.com/shop/blog/' . $post->getUrlKey())
        ->toContain('https://example.com/shop/blog/category/' . Mage::helper('blog')->getCategoryUrlPath($category))
        ->each->toStartWith('https://example.com/shop/blog');
    expect($matches[1])->each->not->toEndWith('/');
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use Maho\DataObject;
use Maho\Storage\Mount;
use Maho\Storage\MountRegistry;
use Tests\MahoBackendTestCase;

uses(MahoBackendTestCase::class);

final class SitemapLastmodTestSitemap extends Mage_Sitemap_Model_Sitemap
{
    #[\Override]
    public function save(): static
    {
        return $this;
    }
}

/** @return array<string, ?string> */
function sitemapLastmods(Mount $mount, string $filename): array
{
    $xml = new SimpleXMLElement($mount->read($filename), LIBXML_NONET);
    $dates = [];
    foreach ($xml->children() as $entry) {
        $dates[(string) $entry->loc] = isset($entry->lastmod) ? (string) $entry->lastmod : null;
    }
    return $dates;
}

beforeEach(function (): void {
    $this->root = Mage::getBaseDir('var') . '/sitemap-lastmod-' . bin2hex(random_bytes(8));
    mkdir($this->root, 0700, true);
    $this->mount = new Mount('sitemaps', new LocalFilesystemAdapter($this->root), $this->root);
    MountRegistry::register($this->mount);
    $this->store = Mage::app()->getDefaultStoreView();
    $this->sitemap = new SitemapLastmodTestSitemap()
        ->setStoreId((int) $this->store->getId())
        ->setSitemapPath('/')
        ->setSitemapFilename('sitemap.xml');
});

afterEach(function (): void {
    MountRegistry::reset();
    array_map(unlink(...), glob($this->root . '/*'));
    rmdir($this->root);
});

it('writes each record modification date and keeps the sitemap index date', function (): void {
    $storeId = (int) $this->store->getId();
    $root = Mage::getModel('catalog/category')->load($this->store->getRootCategoryId());
    $entityType = Mage::getSingleton('eav/config')->getEntityType('catalog_product');
    $resource = Mage::getSingleton('core/resource');
    $write = $resource->getConnection('core_write');
    $slug = 'sitemap-lastmod-' . bin2hex(random_bytes(8));

    $category = Mage::getModel('catalog/category')->setName('Sitemap Category')->setUrlKey($slug . '-cat')
        ->setPath($root->getPath())->setIsActive(1)->setIncludeInMenu(1)->setStoreId(0);
    $product = Mage::getModel('catalog/product')->setName('Sitemap Product')->setSku($slug)
        ->setTypeId('simple')->setAttributeSetId((int) $entityType->getDefaultAttributeSetId())
        ->setUrlKey($slug . '-product')->setStatus(Mage_Catalog_Model_Product_Status::STATUS_ENABLED)
        ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)->setPrice(1)
        ->setWebsiteIds([(int) $this->store->getWebsiteId()]);
    $page = Mage::getModel('cms/page')->setTitle('Sitemap Page')->setIdentifier($slug . '-page')
        ->setContent('<p>Example sitemap page.</p>')->setRootTemplate('one_column')->setIsActive()->setStores([0]);
    $blog = Mage::getModel('blog/post')->setTitle('Sitemap Post')->setUrlKey($slug . '-post')
        ->setContent('<p>Example sitemap post.</p>')->setIsActive(true)->setPublishDate('2024-01-01')->setStores([0]);
    $blogCategory = Mage::getModel('blog/category')->setName('Sitemap Blog Category')->setUrlKey($slug . '-blog-cat')
        ->setIsActive(true)->setParentId(0)->setStores([0]);
    $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, $page->getIdentifier());
    $this->store->setConfig('sitemap/generate/max_urls_per_file', 50000);
    $this->store->setConfig('sitemap/category/lastmod', '1');
    $this->store->setConfig('sitemap/product/lastmod', '1');
    $this->store->setConfig('sitemap/page/lastmod', '1');
    $this->store->setConfig('blog/sitemap/lastmod', '1');
    $this->store->setConfig('blog/general/enable_categories', '1');

    try {
        $category->save();
        $product->save();
        $page->save();
        $blog->save();
        $blogCategory->save();
        $write->update($resource->getTableName('catalog/category'), ['updated_at' => '2025-01-02 18:00:00'], ['entity_id = ?' => $category->getId()]);
        $write->update($resource->getTableName('catalog/product'), ['updated_at' => '2025-02-03 03:17:29'], ['entity_id = ?' => $product->getId()]);
        $write->update($resource->getTableName('cms/page'), ['update_time' => '2025-03-04 23:59:59'], ['page_id = ?' => $page->getId()]);
        $write->update($blog->getResource()->getEntityTable(), ['updated_at' => '2025-04-05 01:00:00'], ['entity_id = ?' => $blog->getId()]);
        $write->update($blogCategory->getResource()->getEntityTable(), ['updated_at' => '2025-05-06 02:00:00'], ['entity_id = ?' => $blogCategory->getId()]);

        $this->sitemap->generateXml();

        $base = $this->store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK);
        $categoryUrl = Mage::getResourceModel('sitemap/catalog_category')->getCollection($storeId)[$category->getId()]->getUrl();
        $productUrl = Mage::getResourceModel('sitemap/catalog_product')->getCollection($storeId)[$product->getId()]->getUrl();
        $helper = Mage::helper('core/url');
        $url = static fn(string $path): string => $helper->keepBasePathSlash($helper->addOrRemoveTrailingSlash($base . $path, $storeId), $storeId, false);
        $blogUrl = Mage::app()->withStore($storeId, $blog->getUrl(...));
        $blogIndex = Mage::helper('blog')->getBlogUrl($storeId);
        $blogCategoryUrl = Mage::app()->withStore($storeId, $blogCategory->getUrl(...));
        $blogDates = sitemapLastmods($this->mount, 'sitemap-blog.xml');

        expect(sitemapLastmods($this->mount, 'sitemap-categories.xml'))->toHaveKey($url($categoryUrl), '2025-01-02')
            ->and(sitemapLastmods($this->mount, 'sitemap-products.xml'))->toHaveKey($url($productUrl), '2025-02-03')
            ->and(sitemapLastmods($this->mount, 'sitemap-pages.xml'))->toHaveKey($base, '2025-03-04')
            ->and($blogDates)->toHaveKey($blogUrl, '2025-04-05')->toHaveKey($blogCategoryUrl, '2025-05-06')
            ->toHaveKey($blogIndex)->and($blogDates[$blogIndex])->toBeNull()
            ->and(sitemapLastmods($this->mount, 'sitemap.xml'))->toHaveKey($base . 'sitemap-pages.xml', Mage_Core_Model_Locale::todayUtc());
    } finally {
        foreach ([$blogCategory, $blog, $page, $product, $category] as $record) {
            if ($record->getId()) {
                $record->delete();
            }
        }
    }
});

it('omits invalid entity dates and keeps explicit dates for extensions', function (): void {
    $base = $this->store->getBaseUrl();
    $items = [
        new DataObject(['url' => 'blank', 'sitemap_updated_at' => null]),
        new DataObject(['url' => 'zero', 'sitemap_updated_at' => '0000-00-00 00:00:00']),
        new DataObject(['url' => 'invalid', 'sitemap_updated_at' => '2025-02-30 00:00:00']),
        new DataObject(['url' => 'future', 'sitemap_updated_at' => '9999-01-01 00:00:00']),
    ];
    $this->sitemap->writeSingleSitemapFile('pages', $items, $base, '2025-01-06', '', 0.0, 1, count($items), 100);
    expect(array_values(sitemapLastmods($this->mount, 'sitemap-pages.xml')))->toBe([null, null, null, null]);

    $extensionItem = new DataObject(['url' => 'extension-product', 'updated_at' => '2025-06-07 18:00:00']);
    $this->sitemap->writeSingleSitemapFile('external', [$extensionItem], $base, '2025-01-06', '', 0.0, 1, 1, 100);
    expect(array_values(sitemapLastmods($this->mount, 'sitemap-external.xml')))->toBe(['2025-01-06']);

    $this->sitemap->writeSingleSitemapFile('products', [$extensionItem], $base, '2025-01-06', '', 0.0, 1, 1, 100);
    expect(array_values(sitemapLastmods($this->mount, 'sitemap-products.xml')))->toBe(['2025-01-06']);

    $this->sitemap->writeSingleSitemapFile('products', [new DataObject(['url' => 'disabled', 'sitemap_updated_at' => '2025-06-07 18:00:00'])], $base, '', '', 0.0, 1, 1, 100);
    expect(array_values(sitemapLastmods($this->mount, 'sitemap-products.xml')))->toBe([null]);
});

<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoFrontendTestCase::class);

/**
 * The admin saves "identifier|page_id" in a CMS page setting when two pages share the identifier.
 * The sitemap and llms.txt must still find the home page and the no-route page.
 */
function cmsPageSettingCreatePage(): Mage_Cms_Model_Page
{
    $identifier = 'test-setting-' . uniqid();
    $page = Mage::getModel('cms/page');
    $page->setTitle('A ' . $identifier)
        ->setIdentifier($identifier)
        ->setIsActive()
        ->setRootTemplate('one_column')
        ->setStores([0])
        ->setContent('<p>' . $identifier . '</p>')
        ->save();
    return $page;
}

/**
 * @return list<string>
 */
function cmsPageSettingSitemapUrls(int $storeId): array
{
    $sitemap = new class extends Mage_Sitemap_Model_Sitemap {
        /** @var list<string> */
        public array $urls = [];

        public function collectPageUrls(int $storeId): array
        {
            $this->generatePagesSitemap($storeId, 'http://localhost/', '2026-01-01', 50000);
            return $this->urls;
        }

        #[\Override]
        public function writeSingleSitemapFile(string $type, array $items, string $baseUrl, string $lastmod, string $changefreq, float $priority, int $pageNumber, int $totalItems, int $maxUrlsPerFile): void
        {
            foreach ($items as $item) {
                $this->urls[] = (string) $item->getUrl();
            }
        }
    };
    return $sitemap->collectPageUrls($storeId);
}

beforeEach(function () {
    $this->store = Mage::app()->getStore();
    $this->page = cmsPageSettingCreatePage();
    $this->store->setConfig(
        Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE,
        $this->page->getIdentifier() . '|' . $this->page->getId(),
    );
});

afterEach(function () {
    $this->page->delete();
});

it('gives the home page the base URL in the sitemap', function () {
    $urls = cmsPageSettingSitemapUrls((int) $this->store->getId());

    expect($urls)->toContain('')
        ->and($urls)->not->toContain($this->page->getIdentifier());
});

it('does not list the home page in llms.txt', function () {
    $store = $this->store;
    $pageLinks = fn(): string => implode("\n", Mage::getSingleton('sitemap/llms')->getPageLinks($store));

    expect($pageLinks())->not->toContain($this->page->getTitle());

    $store->setConfig(Mage_Cms_Helper_Page::XML_PATH_HOME_PAGE, 'no-such-page');
    expect($pageLinks())->toContain($this->page->getTitle());
});

it('does not list the no-route page in the sitemap', function () {
    $noRoute = cmsPageSettingCreatePage();
    $saved = $this->store->getConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE);
    $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $noRoute->getIdentifier() . '|' . $noRoute->getId());

    try {
        expect(cmsPageSettingSitemapUrls((int) $this->store->getId()))->not->toContain($noRoute->getIdentifier());
    } finally {
        $this->store->setConfig(Mage_Cms_Helper_Page::XML_PATH_NO_ROUTE_PAGE, $saved);
        $noRoute->delete();
    }
});

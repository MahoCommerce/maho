<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The XML sitemap lists the blog only for a store view where the storefront shows it (#1348).
 */

/**
 * Run the sitemap observer of the blog for the store view $storeId. Return the number of blog files that it writes.
 */
function sitemapBlogGateFiles(int $storeId): int
{
    $sitemap = new class extends Mage_Sitemap_Model_Sitemap {
        public int $files = 0;

        #[\Override]
        public function writeSingleSitemapFile(string $type, array $items, string $baseUrl, string $lastmod, string $changefreq, float $priority, int $pageNumber, int $totalItems, int $maxUrlsPerFile): void
        {
            $this->files++;
        }
    };
    $event = new \Maho\Event([
        'sitemap' => $sitemap,
        'store_id' => $storeId,
        'base_url' => Mage::app()->getStore($storeId)->getBaseUrl(),
        'date' => '2026-01-01',
        'max_urls_per_file' => 50000,
    ]);
    new Maho_Blog_Model_Observer()->addBlogToSitemap(new \Maho\Event\Observer(['event' => $event]));
    return $sitemap->files;
}

beforeEach(function (): void {
    $this->store = Mage::app()->getDefaultStoreView();
    $this->post = Mage::getModel('blog/post')
        ->setTitle('Sitemap gate post')
        ->setContent('Content')
        ->setUrlKey('sitemap-gate-' . uniqid())
        ->setStores([0])
        ->setIsActive(true)
        ->setPublishDate('2025-01-15')
        ->save();
});

afterEach(function (): void {
    $this->store->setConfig('advanced/modules_disable_output/Maho_Blog', '0');
    $this->post->delete();
});

it('lists the blog of a store view where the blog is on', function (): void {
    expect(sitemapBlogGateFiles((int) $this->store->getId()))->toBe(1);
});

it('lists no blog URL of a store view where the blog output is off', function (): void {
    $this->store->setConfig('advanced/modules_disable_output/Maho_Blog', '1');

    expect(sitemapBlogGateFiles((int) $this->store->getId()))->toBe(0);
});

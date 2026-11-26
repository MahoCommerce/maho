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
 * Run the sitemap observer of the blog for the store view $storeId. Return the items of each blog file that it writes.
 *
 * @return list<array<\Maho\DataObject>>
 */
function sitemapBlogGateFiles(int $storeId): array
{
    $sitemap = new class extends Mage_Sitemap_Model_Sitemap {
        /** @var list<array<\Maho\DataObject>> */
        public array $files = [];

        #[\Override]
        public function writeSingleSitemapFile(string $type, array $items, string $baseUrl, string $lastmod, string $changefreq, float $priority, int $pageNumber, int $totalItems, int $maxUrlsPerFile): void
        {
            $this->files[] = $items;
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
    expect(sitemapBlogGateFiles((int) $this->store->getId()))->toHaveCount(1);
});

it('lists no blog URL of a store view where the blog output is off', function (): void {
    $this->store->setConfig('advanced/modules_disable_output/Maho_Blog', '1');

    expect(sitemapBlogGateFiles((int) $this->store->getId()))->toBeEmpty();
});

it('lists blog image URLs with the unsecure media URL of the sitemap store', function (): void {
    $storeId = (int) $this->store->getId();
    $this->post->setImage('cover.jpg')->save();
    $this->store->setConfig('blog/sitemap/include_images', '1');
    $this->store->setConfig('web/unsecure/base_media_url', 'https://images.example.test/shop/');
    $this->store->setConfig('web/secure/base_media_url', 'https://secure-images.example.test/shop/');

    // An admin page on HTTPS: the image URL must not follow the scheme of this request.
    $https = $_SERVER['HTTPS'] ?? null;
    $_SERVER['HTTPS'] = 'on';
    try {
        $files = Mage::app()->withStore(Mage_Core_Model_App::ADMIN_STORE_ID, fn() => sitemapBlogGateFiles($storeId));
    } finally {
        if ($https === null) {
            unset($_SERVER['HTTPS']);
        } else {
            $_SERVER['HTTPS'] = $https;
        }
    }

    expect(array_map(fn(\Maho\DataObject $item) => $item->getImageUrl(), $files[0]))
        ->toContain('https://images.example.test/shop/blog/cover.jpg');
});

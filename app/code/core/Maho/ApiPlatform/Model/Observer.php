<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * API Platform Observer.
 *
 * Adds RFC 8594 deprecation headers to legacy SOAP/REST API responses, and points storefront
 * responses at the API catalog.
 */
class Maho_ApiPlatform_Model_Observer
{
    /**
     * Legacy API path prefixes to match
     */
    private const LEGACY_API_PATHS = [
        '/api/soap',
        '/api/v2_soap',
        '/api/rest',
    ];

    /**
     * Add deprecation headers to legacy API responses (RFC 8594)
     *
     * @param \Maho\Event\Observer $_observer Observer instance (required by framework)
     */
    #[Maho\Config\Observer('controller_front_send_response_before')]
    public function addDeprecationHeaders(\Maho\Event\Observer $_observer): void
    {
        $app = Mage::app();
        $request = $app->getRequest();
        $response = $app->getResponse();

        if (!$request || !$response) {
            return;
        }

        $path = $request->getPathInfo() ?? '';
        $isLegacyApi = array_any(self::LEGACY_API_PATHS, fn($pattern) => str_starts_with($path, $pattern));

        if (!$isLegacyApi) {
            return;
        }

        // RFC 8594 Deprecation header
        $response->setHeader('Deprecation', 'true', true);

        // Link to successor version
        $successorPath = $this->getSuccessorPath($path);
        if ($successorPath) {
            $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($successorPath, '/');
            $response->setHeader('Link', "<{$fullUrl}>; rel=\"successor-version\"", true);
        }

        // Warning header for human readers
        $response->setHeader(
            'Warning',
            '299 - "This API is deprecated. Please migrate to /api/rest/v2/. See documentation at /api/docs"',
            true,
        );
    }

    /**
     * RFC 9727 discovery without parsing any markup: the catalog is one link away from every
     * storefront response. Skipped under /api, where the deprecation header owns the Link field.
     */
    #[Maho\Config\Observer('controller_front_send_response_before', area: 'frontend')]
    public function addApiCatalogLink(\Maho\Event\Observer $_observer): void
    {
        $app = Mage::app();
        $request = $app->getRequest();
        $response = $app->getResponse();

        if (!$request || !$response || str_starts_with($request->getPathInfo() ?? '', '/api')) {
            return;
        }

        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('apiplatform');
        if (!$helper->hasPublicApi()) {
            return;
        }

        $url = $helper->getRequestRoot() . '/' . Maho_ApiPlatform_Model_Discovery::PATH_API_CATALOG;
        $response->setHeader('Link', "<{$url}>; rel=\"api-catalog\"", true);
    }

    /**
     * Map legacy paths to new API Platform paths
     *
     * @param string $legacyPath The legacy API path
     * @return string The successor API path
     */
    private function getSuccessorPath(string $legacyPath): string
    {
        $mappings = [
            '/api/rest/products' => 'api/rest/v2/products',
            '/api/rest/customers' => 'api/rest/v2/customers',
            '/api/rest/orders' => 'api/rest/v2/orders',
            '/api/rest/stockitems' => 'api/rest/v2/stock-items',
        ];

        foreach ($mappings as $legacy => $new) {
            if (str_starts_with($legacyPath, $legacy)) {
                return $new;
            }
        }

        // Default to API docs for unmapped endpoints
        return 'api/docs';
    }

    /**
     * Invalidate API cache when a product is saved or deleted
     */
    #[Maho\Config\Observer('catalog_product_save_after')]
    #[Maho\Config\Observer('catalog_product_delete_after')]
    public function invalidateProductCache(\Maho\Event\Observer $observer): void
    {
        $this->cleanApiCache(['API_PRODUCTS']);

        $product = $observer->getEvent()->getProduct();
        if ($product && $product->getId()) {
            $this->cleanApiCache(["API_PRODUCT_{$product->getId()}"]);
        }
    }

    /**
     * Invalidate API cache when a category is saved
     */
    #[Maho\Config\Observer('catalog_category_save_after')]
    public function invalidateCategoryCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_PRODUCTS']);
    }

    /**
     * Invalidate API cache when stock is updated
     */
    #[Maho\Config\Observer('cataloginventory_stock_item_save_after')]
    public function invalidateStockCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_PRODUCTS']);
    }

    /**
     * Invalidate API cache when prices are updated (catalog rules, etc.)
     */
    #[Maho\Config\Observer('catalogrule_after_apply')]
    public function invalidatePriceCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_PRODUCTS']);
    }

    /**
     * Cached product DTOs carry prices converted at the rate current when they
     * were built, so a rate change must flush them or the API serves the old
     * rate until the TTL lapses.
     */
    #[Maho\Config\Observer('directory_currency_rates_save_after')]
    public function invalidateCurrencyRateCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_PRODUCTS']);
    }

    /**
     * Invalidate API reviews cache when a review is saved/approved
     */
    #[Maho\Config\Observer('review_save_after')]
    public function invalidateReviewCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_REVIEWS']);
    }

    /**
     * Invalidate config-derived API caches when configuration or store metadata
     * changes. StoreConfigProvider caches for 24h and CountryProvider for 1h;
     * without this, edits (store name, base URLs, allowed countries) would be
     * served stale until the TTL lapses.
     */
    #[Maho\Config\Observer('core_config_data_save_after')]
    #[Maho\Config\Observer('core_config_data_delete_after')]
    #[Maho\Config\Observer('core_store_save_after')]
    public function invalidateStoreConfigCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_STORE_CONFIG', 'API_COUNTRIES']);
    }

    /**
     * The compiled Symfony container lives outside the Maho cache backend and
     * bakes in expression function names, so a full flush must remove it too.
     * Renaming first keeps the swap atomic for in-flight requests; the doomed
     * tree (and any leftover from an interrupted flush) is deleted afterwards.
     */
    #[Maho\Config\Observer('adminhtml_cache_flush_all')]
    public function flushCompiledApiContainer(\Maho\Event\Observer $_observer): void
    {
        $dir = BP . '/var/cache/api_platform';
        if (is_dir($dir) && !@rename($dir, $dir . '.old.' . uniqid())) {
            \Maho\Io\File::rmdirRecursive($dir);
        }
        foreach (glob($dir . '.old.*') ?: [] as $doomed) {
            \Maho\Io\File::rmdirRecursive($doomed);
        }
    }

    /**
     * Purge idempotency-key rows older than the listener's TTL window.
     *
     * The IdempotencyListener stores response replays for 24 hours; rows beyond
     * that are useless. Runs daily so the table doesn't grow unbounded.
     */
    #[Maho\Config\CronJob('apiplatform_idempotency_cleanup', schedule: '0 3 * * *')]
    public function cleanupIdempotencyKeys(): void
    {
        try {
            $resource = Mage::getSingleton('core/resource');
            $write = $resource->getConnection('core_write');
            $table = $resource->getTableName(\Maho\ApiPlatform\EventListener\IdempotencyListener::TABLE);
            $cutoff = Mage::app()->getLocale()->formatDateForDb(
                '-' . \Maho\ApiPlatform\EventListener\IdempotencyListener::TTL_HOURS . ' hours',
            );
            $write->delete($table, $write->quoteInto('created_at < ?', $cutoff));
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Purge spent authorization codes and refresh tokens, and grants revoked
     * long enough ago that nobody is looking at them any more.
     *
     * Codes live for seconds and refresh tokens for a day, so without this the
     * table grows by one row per token exchange forever.
     */
    #[Maho\Config\CronJob('apiplatform_oauth_cleanup', schedule: '15 3 * * *')]
    public function cleanupOauthTokens(): void
    {
        try {
            /** @var Maho_ApiPlatform_Model_Resource_Oauth_Token $resource */
            $resource = Mage::getResourceSingleton('apiplatform/oauth_token');
            $resource->purgeExpired();
        } catch (\Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Get the configured cache TTL in seconds
     */
    public static function getCacheTtl(): int
    {
        $ttl = (int) Mage::getStoreConfig('apiplatform/cache/ttl');
        return $ttl > 0 ? $ttl : 300;
    }

    /**
     * Clean API cache by tags.
     *
     * Every api_data consumer caches under one of these tags, so the clean is the
     * whole job: never invalidate the type here, or ordinary catalog activity would
     * leave the admin permanently asking for a refresh that has nothing to refresh.
     *
     * @param string[] $tags Cache tags to clean
     */
    private function cleanApiCache(array $tags): void
    {
        try {
            Mage::app()->getCache()->clean($tags);
        } catch (\Throwable $e) {
            Mage::log('Failed to clean API cache: ' . $e->getMessage(), Mage::LOG_WARNING);
        }
    }
}

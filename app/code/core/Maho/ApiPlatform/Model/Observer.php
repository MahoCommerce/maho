<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * API Platform Observer
 *
 * Adds RFC 8594 deprecation headers to legacy SOAP/REST API responses
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

        // Check if this is a legacy API request
        $isLegacyApi = false;
        foreach (self::LEGACY_API_PATHS as $pattern) {
            if (str_starts_with($path, $pattern)) {
                $isLegacyApi = true;
                break;
            }
        }

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
     * Invalidate API reviews cache when a review is saved/approved
     */
    #[Maho\Config\Observer('review_save_after')]
    public function invalidateReviewCache(\Maho\Event\Observer $_observer): void
    {
        $this->cleanApiCache(['API_REVIEWS']);
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
     * Get the configured cache TTL in seconds
     */
    public static function getCacheTtl(): int
    {
        $ttl = (int) Mage::getStoreConfig('apiplatform/cache/ttl');
        return $ttl > 0 ? $ttl : 300;
    }

    /**
     * Clean API cache by tags and mark the api_data cache type as invalidated
     *
     * @param string[] $tags Cache tags to clean
     */
    private function cleanApiCache(array $tags): void
    {
        try {
            Mage::app()->getCache()->clean($tags);
            Mage::app()->getCache()->invalidateType('api_data');
        } catch (\Throwable $e) {
            Mage::log('Failed to clean API cache: ' . $e->getMessage(), Mage::LOG_WARNING);
        }
    }
}

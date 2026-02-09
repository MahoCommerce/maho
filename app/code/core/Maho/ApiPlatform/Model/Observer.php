<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
    public function addDeprecationHeaders(\Maho\Event\Observer $_observer): void
    {
        $app = Mage::app();
        if (!$app) {
            return;
        }

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

        /** @var Maho_ApiPlatform_Helper_Data $helper */
        $helper = Mage::helper('maho_apiplatform');
        $sunsetDate = $helper->getLegacySunsetDate();

        // RFC 8594 Deprecation header
        $response->setHeader('Deprecation', 'true', true);

        // RFC 8594 Sunset header with validation
        $sunsetTimestamp = strtotime($sunsetDate);
        if ($sunsetTimestamp === false) {
            Mage::log(
                'Invalid legacy_sunset_date configuration: ' . $sunsetDate,
                Mage::LOG_ERROR,
                'apiplatform.log',
            );
            $sunsetTimestamp = strtotime(Maho_ApiPlatform_Helper_Data::DEFAULT_LEGACY_SUNSET_DATE);
        }
        $sunsetFormatted = gmdate('D, d M Y H:i:s', $sunsetTimestamp) . ' GMT';
        $response->setHeader('Sunset', $sunsetFormatted, true);

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
            '299 - "This API is deprecated. Please migrate to /api/v2/. See documentation at /api/v2/docs"',
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
            '/api/rest/products' => 'api/v2/products',
            '/api/rest/customers' => 'api/v2/customers',
            '/api/rest/orders' => 'api/v2/orders',
            '/api/rest/stockitems' => 'api/v2/stock-items',
        ];

        foreach ($mappings as $legacy => $new) {
            if (str_starts_with($legacyPath, $legacy)) {
                return $new;
            }
        }

        // Default to API docs for unmapped endpoints
        return 'api/v2/docs';
    }

    /**
     * Invalidate API cache when a product is saved
     */
    public function invalidateProductCache(\Maho\Event\Observer $observer): void
    {
        $mode = $this->getAutoCleanMode();
        if ($mode === 'disabled') {
            return;
        }
        // 'all' and 'product' both trigger on product saves
        if ($mode === 'all' || $mode === 'product') {
            $this->cleanApiCache(['API_PRODUCTS']);

            $product = $observer->getEvent()->getProduct();
            if ($product && $product->getId()) {
                $this->cleanApiCache(["API_PRODUCT_{$product->getId()}"]);
            }
        }
    }

    /**
     * Invalidate API cache when a category is saved
     */
    public function invalidateCategoryCache(\Maho\Event\Observer $observer): void
    {
        $mode = $this->getAutoCleanMode();
        if ($mode === 'all' || $mode === 'product') {
            $this->cleanApiCache(['API_PRODUCTS']);
        }
    }

    /**
     * Invalidate API cache when stock is updated
     */
    public function invalidateStockCache(\Maho\Event\Observer $observer): void
    {
        $mode = $this->getAutoCleanMode();
        if ($mode === 'all' || $mode === 'inventory') {
            $this->cleanApiCache(['API_PRODUCTS']);
        }
    }

    /**
     * Invalidate API cache when prices are updated (catalog rules, etc.)
     */
    public function invalidatePriceCache(\Maho\Event\Observer $observer): void
    {
        $mode = $this->getAutoCleanMode();
        if ($mode === 'all' || $mode === 'product') {
            $this->cleanApiCache(['API_PRODUCTS']);
        }
    }

    /**
     * Invalidate API reviews cache when a review is saved/approved
     */
    public function invalidateReviewCache(\Maho\Event\Observer $observer): void
    {
        $mode = $this->getAutoCleanMode();
        if ($mode === 'all') {
            $this->cleanApiCache(['API_REVIEWS']);
        }
    }

    /**
     * Get the configured auto-clean mode
     */
    private function getAutoCleanMode(): string
    {
        return Mage::getStoreConfig('maho_apiplatform/cache/auto_clean') ?: 'all';
    }

    /**
     * Get the configured cache TTL in seconds
     */
    public static function getCacheTtl(): int
    {
        $ttl = (int) Mage::getStoreConfig('maho_apiplatform/cache/ttl');
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

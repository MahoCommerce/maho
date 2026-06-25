<?php

/**
 * Structured data configuration accessors and shared schema helpers.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Maho_StructuredData';

    public const XML_PATH_ENABLED = 'catalog/structured_data/enabled';
    public const XML_PATH_PRODUCT_INCLUDE_REVIEWS = 'catalog/structured_data/product/include_reviews';
    public const XML_PATH_PRODUCT_BRAND_ATTRIBUTE = 'catalog/structured_data/product/brand_attribute';
    public const XML_PATH_PRODUCT_GTIN_ATTRIBUTE = 'catalog/structured_data/product/gtin_attribute';
    public const XML_PATH_PRODUCT_MPN_ATTRIBUTE = 'catalog/structured_data/product/mpn_attribute';
    public const XML_PATH_ORGANIZATION_TYPE = 'catalog/structured_data/organization/type';

    public const SCHEMA = 'https://schema.org/';

    /** @var array<int, string> social profile config paths (general business identity, shared across features) */
    public const SOCIAL_PATHS = [
        'general/social_profiles/facebook_url',
        'general/social_profiles/twitter_url',
        'general/social_profiles/instagram_url',
        'general/social_profiles/linkedin_url',
    ];

    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    public function includeReviews(int|string|null $store = null): bool
    {
        // Reviews are a soft dependency: the module declares no dependency on Mage_Review, so guard
        // here (the single chokepoint for both aggregateRating and review nodes) to avoid calling
        // Mage::getModel('review/review') — which returns false and fatals — when it is disabled.
        if (!Mage::helper('core')->isModuleEnabled('Mage_Review')) {
            return false;
        }
        return Mage::getStoreConfigFlag(self::XML_PATH_PRODUCT_INCLUDE_REVIEWS, $store);
    }

    /**
     * Wrap a schema.org graph in a JSON-LD <script> tag. Single source of truth for the markup,
     * shared by the jsonld.phtml template and the listing-page observer. Uses JSON_HEX_TAG and
     * JSON_HEX_AMP so any literal "</script>" (or other "<", ">", "&") in admin- or
     * customer-controlled string data is escaped and cannot break out of the script element.
     *
     * @param array<string, mixed> $data
     */
    public function renderJsonLdScript(array $data): string
    {
        if ($data === []) {
            return '';
        }
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP);
        return '<script type="application/ld+json">' . $json . '</script>';
    }

    /**
     * Build a summary-format ItemList graph from a list of page URLs. Each entry links to the
     * detail page that carries the full markup (Product, BlogPosting, ...) — Google's recommended
     * format for listing pages — so it is reused across product, search and blog listings.
     *
     * @param array<int, string> $urls
     * @return array<string, mixed>
     */
    public function buildItemList(array $urls): array
    {
        $itemListElement = [];
        $position = 1;
        foreach ($urls as $url) {
            $url = (string) $url;
            if ($url === '') {
                continue;
            }

            $itemListElement[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'url' => $url,
            ];
            $position++;
        }

        if ($itemListElement === []) {
            return [];
        }

        return [
            '@context' => self::SCHEMA,
            '@type' => 'ItemList',
            'itemListElement' => $itemListElement,
        ];
    }

    public function getOrganizationName(int|string|null $store = null): string
    {
        $name = trim((string) Mage::getStoreConfig('general/store_information/name', $store));
        if ($name !== '') {
            return $name;
        }
        return (string) Mage::app()->getStore($store)->getFrontendName();
    }

    /**
     * Resolve the organization logo from the theme logo.
     */
    public function getOrganizationLogoUrl(int|string|null $store = null): string
    {
        $logoSrc = (string) Mage::getStoreConfig('design/header/logo_src', $store);
        if ($logoSrc !== '') {
            return (string) Mage::getDesign()->getSkinUrl($logoSrc);
        }

        return '';
    }

    /**
     * Build a publisher Organization node (name + logo) shared by the Article schema.
     *
     * @return array<string, mixed>
     */
    public function getPublisherData(int|string|null $store = null): array
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => $this->getOrganizationName($store),
        ];

        $logo = $this->getOrganizationLogoUrl($store);
        if ($logo !== '') {
            $publisher['logo'] = ['@type' => 'ImageObject', 'url' => $logo];
        }

        return $publisher;
    }

    public function getBrandAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_BRAND_ATTRIBUTE, $store));
    }

    public function getGtinAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_GTIN_ATTRIBUTE, $store));
    }

    public function getMpnAttribute(int|string|null $store = null): string
    {
        return trim((string) Mage::getStoreConfig(self::XML_PATH_PRODUCT_MPN_ATTRIBUTE, $store));
    }

    /**
     * Map a product's stock state to a schema.org ItemAvailability URL.
     */
    public function getAvailabilityUrl(Mage_Catalog_Model_Product $product): string
    {
        if (!$product->isSaleable()) {
            return self::SCHEMA . 'OutOfStock';
        }

        $stockItem = $product->getStockItem();
        if (!$stockItem) {
            // Saleable with no managed stock (e.g. virtual/downloadable): treat as in stock.
            return self::SCHEMA . 'InStock';
        }

        if (!$stockItem->getIsInStock()) {
            return self::SCHEMA . 'OutOfStock';
        }

        // Stock not managed by quantity: rely on the in-stock flag only.
        if (!$stockItem->getManageStock()) {
            return self::SCHEMA . 'InStock';
        }

        // Composite products (configurable/grouped/bundle) track stock on their children; the parent
        // stock row's qty is forced to 0, so the qty-based checks below would wrongly report a
        // saleable, in-stock parent as out of stock. isSaleable() + the in-stock flag above already
        // reflect child availability for these types.
        if ($product->isComposite()) {
            return self::SCHEMA . 'InStock';
        }

        $qty = (float) $stockItem->getQty();

        if ($qty <= 0) {
            if ((int) $stockItem->getBackorders() > 0) {
                return self::SCHEMA . 'BackOrder';
            }
            return self::SCHEMA . 'OutOfStock';
        }

        // Reuse the inventory low-stock threshold (cataloginventory/item_options/notify_stock_qty),
        // which the stock item resolves per-product with fallback to the global default.
        $threshold = (float) $stockItem->getNotifyStockQty();
        if ($threshold > 0 && $qty <= $threshold) {
            return self::SCHEMA . 'LimitedAvailability';
        }

        return self::SCHEMA . 'InStock';
    }

    /**
     * Display price (current currency, including tax) for a saleable amount.
     */
    public function getDisplayPrice(Mage_Catalog_Model_Product $product, float $price): float
    {
        /** @var Mage_Tax_Helper_Data $taxHelper */
        $taxHelper = Mage::helper('tax');
        $priceInclTax = (float) $taxHelper->getPrice($product, $price, true);

        return (float) Mage::app()->getStore($product->getStoreId())->convertPrice($priceInclTax);
    }

    /**
     * Format a price as a fixed-2-decimal string, the form Google expects.
     */
    public function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    public function getCurrencyCode(int|string|null $store = null): string
    {
        return Mage::app()->getStore($store)->getCurrentCurrencyCode();
    }

    /**
     * Convert a stored UTC datetime (e.g. created_at, updated_at) to an ISO-8601 string in the
     * store timezone. Returns '' for empty, zero ("0000-...") or unparseable input.
     */
    public function formatUtcDateTime(string $value, int|string|null $store = null): string
    {
        if ($value === '' || str_starts_with($value, '0000')) {
            return '';
        }

        try {
            return Mage::app()->getLocale()
                ->utcToStore(Mage::app()->getStore($store), $value)
                ->format('c');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Reduce an HTML fragment to single-line plain text: strip tags, decode entities (so
     * "Tom &amp; Jerry" becomes "Tom & Jerry"), then collapse runs of whitespace.
     */
    public function toPlainText(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    /**
     * Configured social profile URLs (sameAs).
     *
     * @return array<int, string>
     */
    public function getSocialProfiles(int|string|null $store = null): array
    {
        $profiles = [];
        foreach (self::SOCIAL_PATHS as $path) {
            $url = trim((string) Mage::getStoreConfig($path, $store));
            // Only emit valid http(s) URLs; reject javascript: and other unsafe schemes.
            if ($url !== '' && Mage::helper('core')->isValidUrl($url)) {
                $profiles[] = $url;
            }
        }
        return $profiles;
    }
}

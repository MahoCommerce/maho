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
    public const XML_PATH_PRODUCT_ENABLED = 'catalog/structured_data/product/enabled';
    public const XML_PATH_PRODUCT_INCLUDE_REVIEWS = 'catalog/structured_data/product/include_reviews';
    public const XML_PATH_PRODUCT_BRAND_ATTRIBUTE = 'catalog/structured_data/product/brand_attribute';
    public const XML_PATH_PRODUCT_GTIN_ATTRIBUTE = 'catalog/structured_data/product/gtin_attribute';
    public const XML_PATH_PRODUCT_MPN_ATTRIBUTE = 'catalog/structured_data/product/mpn_attribute';
    public const XML_PATH_PRODUCT_LOW_STOCK_THRESHOLD = 'catalog/structured_data/product/low_stock_threshold';
    public const XML_PATH_BREADCRUMBS_ENABLED = 'catalog/structured_data/breadcrumbs/enabled';
    public const XML_PATH_ORGANIZATION_ENABLED = 'catalog/structured_data/organization/enabled';
    public const XML_PATH_ORGANIZATION_TYPE = 'catalog/structured_data/organization/type';
    public const XML_PATH_ORGANIZATION_LOGO_URL = 'catalog/structured_data/organization/logo_url';
    public const XML_PATH_ORGANIZATION_CONTACT_PHONE = 'catalog/structured_data/organization/contact_phone';
    public const XML_PATH_ORGANIZATION_CONTACT_EMAIL = 'catalog/structured_data/organization/contact_email';
    public const XML_PATH_WEBSITE_ENABLED = 'catalog/structured_data/website/enabled';
    public const XML_PATH_BLOG_ENABLED = 'catalog/structured_data/blog/enabled';

    public const SCHEMA = 'https://schema.org/';

    /** @var array<int, string> social profile config paths */
    public const SOCIAL_PATHS = [
        'catalog/structured_data/organization/facebook_url',
        'catalog/structured_data/organization/twitter_url',
        'catalog/structured_data/organization/instagram_url',
        'catalog/structured_data/organization/linkedin_url',
    ];

    public function isEnabled(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store);
    }

    public function isProductEnabled(int|string|null $store = null): bool
    {
        return $this->isEnabled($store) && Mage::getStoreConfigFlag(self::XML_PATH_PRODUCT_ENABLED, $store);
    }

    public function includeReviews(int|string|null $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_PRODUCT_INCLUDE_REVIEWS, $store);
    }

    public function isBreadcrumbsEnabled(int|string|null $store = null): bool
    {
        return $this->isEnabled($store) && Mage::getStoreConfigFlag(self::XML_PATH_BREADCRUMBS_ENABLED, $store);
    }

    public function isOrganizationEnabled(int|string|null $store = null): bool
    {
        return $this->isEnabled($store) && Mage::getStoreConfigFlag(self::XML_PATH_ORGANIZATION_ENABLED, $store);
    }

    public function isWebsiteEnabled(int|string|null $store = null): bool
    {
        return $this->isEnabled($store) && Mage::getStoreConfigFlag(self::XML_PATH_WEBSITE_ENABLED, $store);
    }

    public function isBlogEnabled(int|string|null $store = null): bool
    {
        return $this->isEnabled($store) && Mage::getStoreConfigFlag(self::XML_PATH_BLOG_ENABLED, $store);
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
     * Resolve the organization logo: the configured URL, otherwise the theme logo.
     */
    public function getOrganizationLogoUrl(int|string|null $store = null): string
    {
        $configured = trim((string) Mage::getStoreConfig(self::XML_PATH_ORGANIZATION_LOGO_URL, $store));
        if ($configured !== '') {
            return $configured;
        }

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

    public function getLowStockThreshold(int|string|null $store = null): int
    {
        return (int) Mage::getStoreConfig(self::XML_PATH_PRODUCT_LOW_STOCK_THRESHOLD, $store);
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

        $qty = (float) $stockItem->getQty();

        if ($qty <= 0) {
            if ((int) $stockItem->getBackorders() > 0) {
                return self::SCHEMA . 'BackOrder';
            }
            return self::SCHEMA . 'OutOfStock';
        }

        $threshold = $this->getLowStockThreshold($product->getStoreId());
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
     * Configured social profile URLs (sameAs).
     *
     * @return array<int, string>
     */
    public function getSocialProfiles(int|string|null $store = null): array
    {
        $profiles = [];
        foreach (self::SOCIAL_PATHS as $path) {
            $url = trim((string) Mage::getStoreConfig($path, $store));
            if ($url !== '') {
                $profiles[] = $url;
            }
        }
        return $profiles;
    }
}

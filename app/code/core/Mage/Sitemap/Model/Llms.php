<?php

/**
 * Builds the llms.txt served at the store root.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Llms
{
    public const XML_PATH_ENABLED = 'crawlers/llms/enabled';
    public const XML_PATH_DESCRIPTION = 'crawlers/llms/description';
    public const XML_PATH_CUSTOM = 'crawlers/llms/custom';

    public function isEnabled(?Mage_Core_Model_Store $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store?->getId());
    }

    public function generate(?Mage_Core_Model_Store $store = null): string
    {
        $store ??= Mage::app()->getStore();
        $storeId = $store->getId();

        $sections = ['# ' . $this->getStoreName($store)];

        $description = $this->getDescription($store);
        if ($description !== '') {
            $sections[] = implode("\n", array_map(
                static fn(string $line): string => rtrim('> ' . $line),
                explode("\n", $description),
            ));
        }

        $details = [
            '- Locale: ' . str_replace('_', '-', (string) Mage::getStoreConfig('general/locale/code', $storeId)),
            '- Currency: ' . $store->getDefaultCurrencyCode(),
        ];
        if (Mage::helper('core')->isModuleEnabled('Maho_StructuredData')
            && Mage::getStoreConfigFlag('catalog/structured_data/enabled', $storeId)
        ) {
            $details[] = '- Structured data: product, category, blog, and CMS pages embed schema.org'
                . ' JSON-LD (price, availability, shipping, returns, ratings)';
        }
        $sections[] = implode("\n", $details);

        /** @var Mage_Sitemap_Model_Robots $robots */
        $robots = Mage::getSingleton('sitemap/robots');
        $sitemapLines = [];
        foreach ($robots->getSitemapUrls($store) as $url) {
            $filename = basename((string) parse_url($url, PHP_URL_PATH));
            $sitemapLines[] = "- [{$filename}]({$url}): XML sitemap";
        }
        if ($sitemapLines !== []) {
            $sections[] = "## Sitemaps\n\n" . implode("\n", $sitemapLines);
        }

        $custom = trim((string) Mage::getStoreConfig(self::XML_PATH_CUSTOM, $storeId));
        if ($custom !== '') {
            $sections[] = $custom;
        }

        return implode("\n\n", $sections) . "\n";
    }

    public function getStoreName(Mage_Core_Model_Store $store): string
    {
        $name = (string) Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME, $store->getId());
        if ($name === '') {
            // getGroup() returns false, not null, for a store with no group.
            $group = $store->getGroup();
            $name = $group ? (string) $group->getName() : '';
        }
        if ($name === '') {
            $name = (string) $store->getName();
        }
        return trim((string) preg_replace('/\s+/', ' ', $name));
    }

    public function getDescription(Mage_Core_Model_Store $store): string
    {
        $description = trim((string) Mage::getStoreConfig(self::XML_PATH_DESCRIPTION, $store->getId()));
        if ($description === '') {
            $description = trim((string) Mage::getStoreConfig('design/head/default_description', $store->getId()));
        }
        return $description;
    }
}

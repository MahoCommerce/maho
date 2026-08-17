<?php

/**
 * Builds the llms.txt and llms-full.txt served at the store root.
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
    public const XML_PATH_FULL_ENABLED = 'crawlers/llms/full_enabled';
    public const XML_PATH_CUSTOM = 'crawlers/llms/custom';

    protected const PAGES_LIMIT = 100;
    protected const CATEGORIES_LIMIT = 50;
    protected const FULL_BYTES_LIMIT = 512000;

    public function isEnabled(?Mage_Core_Model_Store $store = null): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $store?->getId());
    }

    public function isFullEnabled(?Mage_Core_Model_Store $store = null): bool
    {
        return $this->isEnabled($store)
            && Mage::getStoreConfigFlag(self::XML_PATH_FULL_ENABLED, $store?->getId());
    }

    public function generate(?Mage_Core_Model_Store $store = null): string
    {
        $store ??= Mage::app()->getStore();

        $sections = $this->_getHeaderSections($store);

        $pages = $this->getPageLinks($store);
        if ($pages !== []) {
            $sections[] = "## Pages\n\n" . implode("\n", $pages);
        }

        $categories = $this->getCategoryLinks($store);
        if ($categories !== []) {
            $sections[] = "## Categories\n\n" . implode("\n", $categories);
        }

        $search = $this->getSearchUrlTemplate($store);
        if ($search !== '') {
            $sections[] = "## Search\n\n"
                . $this->_link('Product search', $search, 'replace QUERY with the URL-encoded search terms');
        }

        $api = $this->getApiLinks($store);
        if ($api !== []) {
            $sections[] = "## API\n\n" . implode("\n", $api);
        }

        $sitemaps = $this->getSitemapLinks($store);
        if ($sitemaps !== []) {
            $sections[] = "## Sitemaps\n\n" . implode("\n", $sitemaps);
        }

        $storeViews = $this->getStoreViewLinks($store);
        if ($storeViews !== []) {
            $sections[] = "## Other store views\n\n" . implode("\n", $storeViews);
        }

        $custom = trim((string) Mage::getStoreConfig(self::XML_PATH_CUSTOM, $store->getId()));
        if ($custom !== '') {
            $sections[] = $custom;
        }

        return implode("\n\n", $sections) . "\n";
    }

    /**
     * The llms.txt header followed by the full text of the CMS pages. Products and categories stay
     * out: the sitemaps address them one URL at a time.
     */
    public function generateFull(?Mage_Core_Model_Store $store = null): string
    {
        $store ??= Mage::app()->getStore();

        $sections = $this->_getHeaderSections($store);
        $length = strlen(implode("\n\n", $sections));
        $truncated = false;

        $collection = $this->_getPageCollection($store);
        foreach ($collection as $page) {
            $content = $this->toPlainText((string) $page->getContent());
            if ($content === '') {
                continue;
            }

            $section = '## ' . trim((string) $page->getTitle()) . "\n\n"
                . $store->getUrl('', ['_direct' => $page->getIdentifier()]) . "\n\n" . $content;
            $length += strlen($section) + 2;
            if ($length > self::FULL_BYTES_LIMIT) {
                $truncated = true;
                break;
            }

            $sections[] = $section;
        }

        if ($truncated || $collection->getSize() > self::PAGES_LIMIT) {
            $sections[] = '> This file is truncated. The XML sitemap lists every page of this store view.';
        }

        return implode("\n\n", $sections) . "\n";
    }

    /**
     * @return array<int, string>
     */
    protected function _getHeaderSections(Mage_Core_Model_Store $store): array
    {
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
        if ($this->isFullEnabled($store)) {
            $details[] = '- Full text of the pages below: ' . $this->getFileUrl($store, 'llms-full.txt');
        }
        $sections[] = implode("\n", $details);

        return $sections;
    }

    /**
     * @return array<int, string>
     */
    public function getPageLinks(Mage_Core_Model_Store $store): array
    {
        $collection = $this->_getPageCollection($store);

        $links = [];
        foreach ($collection as $page) {
            $links[] = $this->_link((string) $page->getTitle(), $store->getUrl('', ['_direct' => $page->getIdentifier()]));
        }

        $more = $collection->getSize() - count($links);
        if ($more > 0) {
            $links[] = "- and {$more} more pages, listed in the XML sitemap";
        }

        if (Mage::helper('core')->isModuleEnabled('Maho_Blog')
            && !Mage::getStoreConfigFlag('advanced/modules_disable_output/Maho_Blog', $store->getId())
        ) {
            /** @var Maho_Blog_Helper_Data $blog */
            $blog = Mage::helper('blog');
            if ($blog->hasVisiblePosts()) {
                $links[] = $this->_link('Blog', $store->getUrl($blog->getBlogUrlPrefix((int) $store->getId())));
            }
        }

        return $links;
    }

    /**
     * Top level categories only: llms.txt is an index, and the sitemap carries the deeper levels.
     *
     * @return array<int, string>
     */
    public function getCategoryLinks(Mage_Core_Model_Store $store): array
    {
        $rootId = (int) $store->getRootCategoryId();
        if ($rootId === 0) {
            return [];
        }

        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getResourceModel('catalog/category_collection');
        $collection->setStoreId($store->getId())
            ->addAttributeToSelect(['name', 'url_key', 'url_path', 'description'])
            ->addAttributeToFilter('parent_id', $rootId)
            ->addAttributeToFilter('is_active', 1)
            ->addAttributeToFilter('include_in_menu', 1)
            ->setOrder('position', Maho\Db\Select::SQL_ASC)
            ->setPageSize(self::CATEGORIES_LIMIT);

        $links = [];
        foreach ($collection as $category) {
            $name = trim((string) $category->getName());
            if ($name === '') {
                continue;
            }
            $links[] = $this->_link($name, (string) $category->getUrl(), $this->toSingleLine((string) $category->getDescription()));
        }

        $more = $collection->getSize() - count($links);
        if ($more > 0) {
            $links[] = "- and {$more} more categories, listed in the XML sitemap";
        }

        return $links;
    }

    /**
     * The API protocols this install actually serves. Every protocol is off by default and the
     * entry point of a disabled one answers 404, so an agent is told only about what will answer.
     *
     * @return array<int, string>
     */
    public function getApiLinks(Mage_Core_Model_Store $store): array
    {
        if (!Mage::helper('core')->isModuleEnabled('Maho_ApiPlatform')) {
            return [];
        }

        /** @var Maho_ApiPlatform_Helper_Data $api */
        $api = Mage::helper('apiplatform');
        $root = rtrim($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB), '/') . '/';

        $links = [];
        if ($api->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_REST_V2)) {
            $links[] = $this->_link('REST API', $root . 'api/rest/v2', 'OpenAPI description at '
                . $root . 'api/docs, bearer tokens from ' . $root . 'api/rest/v2/auth/token');
        }
        if ($api->isProtocolEnabled(Maho_ApiPlatform_Helper_Data::PROTOCOL_GRAPHQL)) {
            $links[] = $this->_link('GraphQL API', $root . 'api/graphql');
        }
        if ($api->isMcpEnabled()) {
            $links[] = $this->_link('MCP server', $root . 'api/mcp', 'Model Context Protocol over streamable HTTP');
        }
        if ($links !== []) {
            $links[] = $this->_link('API catalog', $root . '.well-known/api-catalog', 'RFC 9727 index of the above');
        }

        return $links;
    }

    /**
     * @return array<int, string>
     */
    public function getSitemapLinks(Mage_Core_Model_Store $store): array
    {
        /** @var Mage_Sitemap_Model_Robots $robots */
        $robots = Mage::getSingleton('sitemap/robots');

        $links = [];
        foreach ($robots->getSitemapUrls($store) as $url) {
            $filename = basename((string) parse_url($url, PHP_URL_PATH));
            $links[] = $this->_link($filename, $url, 'XML sitemap');
        }

        return $links;
    }

    /**
     * One domain serves one llms.txt, so the other store views are reachable only through links.
     *
     * @return array<int, string>
     */
    public function getStoreViewLinks(Mage_Core_Model_Store $store): array
    {
        $current = $this->getFileUrl($store, 'llms.txt');

        $links = [];
        foreach (Mage::app()->getStores() as $other) {
            /** @var Mage_Core_Model_Store $other */
            if ((int) $other->getId() === (int) $store->getId() || !$other->getIsActive() || !$this->isEnabled($other)) {
                continue;
            }

            $url = $this->getFileUrl($other, 'llms.txt');
            if ($url === $current) {
                continue;
            }

            $locale = str_replace('_', '-', (string) Mage::getStoreConfig('general/locale/code', $other->getId()));
            $links[] = $this->_link($this->getStoreName($other), $url, $locale);
        }

        return $links;
    }

    /**
     * The catalog search URL with a placeholder, so an agent can query the catalog directly.
     */
    public function getSearchUrlTemplate(Mage_Core_Model_Store $store): string
    {
        if (!Mage::helper('core')->isModuleEnabled('Mage_CatalogSearch')) {
            return '';
        }

        /** @var Mage_CatalogSearch_Helper_Data $helper */
        $helper = Mage::helper('catalogsearch');

        return $store->getUrl('catalogsearch/result') . '?' . $helper->getQueryParamName() . '=QUERY';
    }

    /**
     * Absolute URL of a file served at the root of a store view.
     */
    public function getFileUrl(Mage_Core_Model_Store $store, string $filename): string
    {
        return rtrim($store->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_LINK), '/') . '/' . $filename;
    }

    /**
     * One markdown list item, the label kept safe for a link.
     */
    protected function _link(string $label, string $url, string $note = ''): string
    {
        $label = trim((string) preg_replace('/[\[\]]/', '', $label));

        return "- [{$label}]({$url})" . ($note !== '' ? ': ' . $note : '');
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

    /**
     * Active CMS pages of this store view, without the pages that serve the store itself: home,
     * 404 and the no-cookies notice.
     *
     * @return Mage_Cms_Model_Resource_Page_Collection
     */
    protected function _getPageCollection(Mage_Core_Model_Store $store)
    {
        $skip = [Mage_Cms_Model_Page::NOROUTE_PAGE_ID];
        foreach (['web/default/cms_home_page', 'web/default/cms_no_route', 'web/default/cms_no_cookies'] as $path) {
            $identifier = trim((string) Mage::getStoreConfig($path, $store->getId()));
            if ($identifier !== '') {
                $skip[] = $identifier;
            }
        }

        /** @var Mage_Cms_Model_Resource_Page_Collection $collection */
        $collection = Mage::getResourceModel('cms/page_collection');
        $collection->addStoreFilter($store->getId())
            ->addFieldToFilter('is_active', 1)
            ->addFieldToFilter('identifier', ['nin' => $skip])
            ->setOrder('title', Maho\Db\Select::SQL_ASC)
            ->setPageSize(self::PAGES_LIMIT);

        return $collection;
    }

    /**
     * Reduce an HTML fragment to plain text, paragraph breaks kept. Directives are stripped:
     * nothing resolves them on this route.
     */
    public function toPlainText(string $html): string
    {
        $html = Mage_Core_Model_Input_Filter_MaliciousCode::stripDirectives($html);
        $html = str_replace(["\r\n", "\r"], "\n", $html);
        $html = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<br\s*/?>#i', "\n", $html);
        $html = (string) preg_replace('#</(p|div|li|tr|h[1-6]|section|article|blockquote)>#i', "\n\n", $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $text = (string) preg_replace('/ ?\n ?/u', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    /**
     * The same reduction on one line, for a link label.
     */
    public function toSingleLine(string $html): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $this->toPlainText($html)));
    }
}

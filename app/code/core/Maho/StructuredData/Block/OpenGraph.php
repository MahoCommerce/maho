<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Block_OpenGraph extends Mage_Core_Block_Abstract
{
    public const XML_PATH_ENABLED = 'catalog/social_sharing/open_graph_enabled';

    #[\Override]
    protected function _toHtml(): string
    {
        if (!Mage::getStoreConfigFlag(self::XML_PATH_ENABLED) || Mage::app()->getStore()->isAdmin()) {
            return '';
        }

        $layout = $this->getLayout();
        $head = $layout->getBlock('head');
        if (!$head instanceof Mage_Page_Block_Html_Head || !$this->_isIndexable($head)) {
            return '';
        }

        $product = Mage::registry('current_product');
        $product = $product instanceof Mage_Catalog_Model_Product ? $product : null;
        $category = Mage::registry('current_category');
        $category = $category instanceof Mage_Catalog_Model_Category ? $category : null;
        $page = $layout->getBlock('cms_page');
        $url = $this->_getCanonicalUrl($head, $product, $category, $page instanceof Mage_Cms_Block_Page ? $page : null);
        $title = trim(html_entity_decode($head->getTitle(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $image = $this->_getImageUrl($product, $category);
        if ($url === '' || $title === '' || $image === '') {
            return '';
        }

        $metadata = [
            'type' => 'website',
            'title' => $title,
            'description' => trim(html_entity_decode(strip_tags((string) $head->getDescription()), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'url' => $url,
            'image' => $image,
            'site_name' => Mage::helper('structureddata')->getOrganizationName(),
            'locale' => (string) Mage::getStoreConfig('general/locale/code'),
        ];

        $html = '';
        foreach ($metadata as $property => $content) {
            if ($content !== '') {
                $html .= '<meta property="og:' . $property . '" content="'
                    . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">' . PHP_EOL;
            }
        }
        return $html;
    }

    private function _isIndexable(Mage_Page_Block_Html_Head $head): bool
    {
        $response = Mage::app()->getResponse();
        $noIndex = '/\b(?:noindex|none)\b/i';
        if ($response->getHttpResponseCode() !== 200 || preg_match($noIndex, (string) $head->getRobots())) {
            return false;
        }

        foreach ($response->getSymfonyResponse()->headers->all('X-Robots-Tag') as $directive) {
            if (preg_match($noIndex, $directive)) {
                return false;
            }
        }
        return true;
    }

    private function _getCanonicalUrl(
        Mage_Page_Block_Html_Head $head,
        ?Mage_Catalog_Model_Product $product,
        ?Mage_Catalog_Model_Category $category,
        ?Mage_Cms_Block_Page $page,
    ): string {
        foreach ($head->getData('items') ?? [] as $item) {
            if ($item['type'] === 'link_rel' && $item['params'] === 'rel="canonical"') {
                return $this->_absoluteHttpUrl(html_entity_decode($item['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        if ($product === null && $category === null) {
            return $this->_absoluteHttpUrl($page?->getCanonicalUrl() ?? '');
        }

        /** @var Mage_Core_Helper_Url $urlHelper */
        $urlHelper = Mage::helper('core/url');
        $storeQuery = $urlHelper->getCanonicalStoreQuery();
        if ($product !== null) {
            $product = clone $product;
            $product->unsetData('url')->unsetData('request_path')->setDoNotUseCategoryId(true);
            return $this->_absoluteHttpUrl($product->getUrlModel()->getUrl($product, ['_query' => $storeQuery]));
        }
        $url = $category->getUrl();
        return $this->_absoluteHttpUrl($storeQuery ? $urlHelper->addRequestParam($url, $storeQuery) : $url);
    }

    private function _getImageUrl(?Mage_Catalog_Model_Product $product, ?Mage_Catalog_Model_Category $category): string
    {
        $image = '';
        if ($product !== null) {
            $file = $product->getImage();
            if ($file && $file !== 'no_selection') {
                $image = $product->getMediaConfig()->getMediaUrl($file);
            }
        } elseif ($category !== null) {
            $image = $category->getImageUrl();
        }

        if ($url = $this->_absoluteHttpUrl($image)) {
            return $url;
        }

        $header = $this->getLayout()->getBlock('header');
        if (!$header instanceof Mage_Page_Block_Html_Header
            || (!$header->getData('logo_src') && !Mage::getStoreConfig('design/header/logo_src'))
        ) {
            return '';
        }
        return $this->_absoluteHttpUrl(html_entity_decode($header->getLogoSrc(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function _absoluteHttpUrl(string $url): string
    {
        $url = trim($url);
        if (str_starts_with($url, '//')) {
            $url = Mage::app()->getRequest()->getScheme() . ':' . $url;
        }

        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || isset($parts['user'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
        ) {
            return '';
        }
        return $url;
    }
}

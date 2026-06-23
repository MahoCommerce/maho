<?php

/**
 * Appends ItemList JSON-LD to product- and blog-list block output wherever they render.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_StructuredData
 */

declare(strict_types=1);

class Maho_StructuredData_Model_Observer
{
    /**
     * Emit a summary-format ItemList for any listing block as it renders, so product listings
     * (category, search, CMS/home widgets) and blog listings (index, category) are covered
     * automatically without per-handle layout wiring.
     */
    #[Maho\Config\Observer('core_block_abstract_to_html_after', area: 'frontend')]
    public function appendListJsonLd(\Maho\Event\Observer $observer): void
    {
        $urls = $this->_resolveListUrls($observer->getEvent()->getBlock());
        if ($urls === []) {
            return;
        }

        $data = Mage::helper('structureddata')->buildItemList($urls);
        if ($data === []) {
            return;
        }

        $transport = $observer->getEvent()->getTransport();
        $transport->setHtml(
            $transport->getHtml()
            . '<script type="application/ld+json">' . Mage::helper('core')->jsonEncode($data) . '</script>',
        );
    }

    /**
     * Resolve the detail-page URLs a listing block displays, gated by the relevant config toggle.
     * Returns an empty array for any block that is not an enabled listing.
     *
     * @return array<int, string>
     */
    protected function _resolveListUrls(Mage_Core_Block_Abstract $block): array
    {
        $helper = Mage::helper('structureddata');

        // Product listings. Category and search render through Mage_Catalog_Block_Product_List;
        // new-products / featured widgets expose their loaded collection via getProductCollection().
        if ($block instanceof Mage_Catalog_Block_Product_List) {
            return $helper->isProductListEnabled()
                ? $this->_urls($block->getLoadedProductCollection(), 'getProductUrl')
                : [];
        }
        if ($block instanceof Mage_Catalog_Block_Product_Abstract) {
            // Related/upsell/crosssell blocks don't set product_collection, so they're skipped.
            $collection = $block->getProductCollection();
            if (is_iterable($collection) && $helper->isProductListEnabled()) {
                return $this->_urls($collection, 'getProductUrl');
            }
            return [];
        }

        // Blog listings (soft dependency on Maho_Blog: instanceof is false when absent).
        if ($block instanceof Maho_Blog_Block_Post_List || $block instanceof Maho_Blog_Block_Category_View) {
            return $helper->isBlogEnabled()
                ? $this->_urls($block->getPosts(), 'getUrl')
                : [];
        }

        return [];
    }

    /**
     * Collect non-empty URLs from a collection by calling the given accessor on each item.
     *
     * @param iterable<Maho\DataObject> $items
     * @return array<int, string>
     */
    protected function _urls(iterable $items, string $accessor): array
    {
        $urls = [];
        foreach ($items as $item) {
            $url = (string) $item->{$accessor}();
            if ($url !== '') {
                $urls[] = $url;
            }
        }
        return $urls;
    }
}

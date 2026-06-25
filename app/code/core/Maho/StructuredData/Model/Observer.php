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
     * Emit a summary-format ItemList for any listing block as it renders.
     *
     * This deliberately hooks the global core_block_abstract_to_html_after rather than wiring
     * blocks per layout handle: product listings appear under several fixed block names (category,
     * search) PLUS dynamically-named widget instances on CMS/home pages, which layout cannot target
     * by name or type. Catching blocks we can neither name nor predict the class of requires
     * inspecting every block as it renders. The handler stays cheap — an isEnabled() short-circuit
     * and an instanceof filter that returns immediately for the (vast) non-listing majority.
     */
    #[Maho\Config\Observer('core_block_abstract_to_html_after', area: 'frontend')]
    public function appendListJsonLd(\Maho\Event\Observer $observer): void
    {
        if (!Mage::helper('structureddata')->isEnabled()) {
            return;
        }

        $urls = $this->_resolveListUrls($observer->getEvent()->getBlock());
        if ($urls === []) {
            return;
        }

        $helper = Mage::helper('structureddata');
        $data = $helper->buildItemList($urls);
        if ($data === []) {
            return;
        }

        // Let other modules enrich or alter the listing graph, mirroring the per-block data events.
        $dataTransport = new Maho\DataObject(['structured_data' => $data]);
        Mage::dispatchEvent('maho_structureddata_itemlist_data', [
            'block' => $observer->getEvent()->getBlock(),
            'transport' => $dataTransport,
        ]);
        $data = (array) $dataTransport->getStructuredData();
        if ($data === []) {
            return;
        }

        $transport = $observer->getEvent()->getTransport();
        $transport->setHtml($transport->getHtml() . $helper->renderJsonLdScript($data));
    }

    /**
     * Resolve the detail-page URLs a listing block displays.
     * Returns an empty array for any block that is not a listing.
     *
     * @return array<int, string>
     */
    protected function _resolveListUrls(Mage_Core_Block_Abstract $block): array
    {
        // Product listings. Category and search render through Mage_Catalog_Block_Product_List;
        // new-products / featured widgets expose their loaded collection via getProductCollection().
        if ($block instanceof Mage_Catalog_Block_Product_List) {
            $collection = $block->getLoadedProductCollection();
            // On a block-cache hit _toHtml() is skipped but this event still fires; the collection
            // is then unloaded, so reusing it would force a full listing query on every request and
            // defeat the cache. Only emit when the block actually rendered (collection already loaded).
            if ($collection instanceof Maho\Data\Collection\Db && !$collection->isLoaded()) {
                return [];
            }
            return $this->_urls($collection, 'getProductUrl');
        }
        if ($block instanceof Mage_Catalog_Block_Product_Abstract) {
            // Related/upsell/crosssell blocks don't set product_collection, so they're skipped.
            $collection = $block->getProductCollection();
            if (is_iterable($collection)) {
                return $this->_urls($collection, 'getProductUrl');
            }
            return [];
        }

        // Blog listings (soft dependency on Maho_Blog: instanceof is false when absent).
        if ($block instanceof Maho_Blog_Block_Post_List || $block instanceof Maho_Blog_Block_Category_View) {
            return $this->_urls($block->getPosts(), 'getUrl');
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

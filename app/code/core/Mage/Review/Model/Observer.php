<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Review
 */

class Mage_Review_Model_Observer
{
    /**
     * Add review summary info for tagged product collection
     *
     * @return $this
     */
    #[Maho\Config\Observer('tag_tag_product_collection_load_after', area: 'frontend')]
    public function tagProductCollectionLoadAfter(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Tag_Model_Resource_Product_Collection $collection */
        $collection = $observer->getEvent()->getCollection();
        Mage::getSingleton('review/review')
            ->appendSummary($collection);

        return $this;
    }

    /**
     * Cleanup product reviews after product delete
     *
     * @return $this
     */
    #[Maho\Config\Observer('catalog_product_delete_after_done', area: 'adminhtml')]
    public function processProductAfterDeleteEvent(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $eventProduct */
        $eventProduct = $observer->getEvent()->getProduct();
        if ($eventProduct && $eventProduct->getId()) {
            Mage::getResourceSingleton('review/review')->deleteReviewsByProductId($eventProduct->getId());
        }

        return $this;
    }

    /**
     * Append review summary before rendering html
     *
     * @return $this
     */
    #[Maho\Config\Observer('catalog_block_product_list_collection', area: 'frontend')]
    public function catalogBlockProductCollectionBeforeToHtml(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Resource_Product_Collection $productCollection */
        $productCollection = $observer->getEvent()->getCollection();
        if ($productCollection instanceof \Maho\Data\Collection) {
            $productCollection->load();
            Mage::getModel('review/review')->appendSummary($productCollection);
        }

        return $this;
    }
}

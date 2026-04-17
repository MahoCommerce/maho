<?php

/**
 * Maho
 *
 * @package    Mage_Review
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

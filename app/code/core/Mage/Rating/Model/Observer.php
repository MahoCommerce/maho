<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

class Mage_Rating_Model_Observer
{
    /**
     * Cleanup product ratings after product delete
     *
     * @return Mage_Rating_Model_Observer
     */
    #[Maho\Config\Observer('catalog_product_delete_after_done', area: 'adminhtml')]
    public function processProductAfterDeleteEvent(\Maho\Event\Observer $observer)
    {
        /** @var Mage_Catalog_Model_Product $eventProduct */
        $eventProduct = $observer->getEvent()->getProduct();
        if ($eventProduct && $eventProduct->getId()) {
            Mage::getResourceSingleton('rating/rating')->deleteAggregatedRatingsByProductId($eventProduct->getId());
        }
        return $this;
    }
}

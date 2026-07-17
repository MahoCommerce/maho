<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Downloadable';

    /**
     * Check is link shareable or not
     *
     * @param Mage_Downloadable_Model_Link | Mage_Downloadable_Model_Link_Purchased_Item $link
     * @return bool
     */
    public function getIsShareable($link)
    {
        $shareable = false;
        switch ($link->getIsShareable()) {
            case Mage_Downloadable_Model_Link::LINK_SHAREABLE_YES:
            case Mage_Downloadable_Model_Link::LINK_SHAREABLE_NO:
                $shareable = (bool) $link->getIsShareable();
                break;
            case Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG:
                $shareable = (bool) Mage::getStoreConfigFlag(
                    Mage_Downloadable_Model_Link::XML_PATH_CONFIG_IS_SHAREABLE,
                );
        }
        return $shareable;
    }

    /**
     * Return true if price in website scope
     *
     * @return bool
     */
    public function getIsPriceWebsiteScope()
    {
        $scope =  (int) Mage::app()->getStore()->getConfig(Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE);
        if ($scope == Mage_Core_Model_Store::PRICE_SCOPE_WEBSITE) {
            return true;
        }
        return false;
    }

    /**
     * Check if current customer has any purchased downloadable products
     */
    public function customerHasDownloadableProducts(): bool
    {
        $customerId = Mage::getSingleton('customer/session')->getCustomerId();
        if (!$customerId) {
            return false;
        }

        return Mage::getResourceModel('downloadable/link_purchased_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->getSize() > 0;
    }
}

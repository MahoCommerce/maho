<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * Msrp items total
 * Collects flag if MSRP price is in use
 */
class Mage_Sales_Model_Quote_Address_Total_Msrp extends Mage_Sales_Model_Quote_Address_Total_Abstract
{
    /**
     * Collect information about MSRP price enabled
     *
     * @return  Mage_Sales_Model_Quote_Address_Total_Msrp
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        parent::collect($address);
        $quote = $address->getQuote();
        $store = Mage::app()->getStore($quote->getStoreId());

        $items = $this->_getAddressItems($address);
        if (!count($items)) {
            return $this;
        }

        $canApplyMsrp = false;
        foreach ($items as $item) {
            if (!$item->getParentItemId()
                && Mage::helper('catalog')->canApplyMsrp(
                    $item->getProductId(),
                    Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type::TYPE_BEFORE_ORDER_CONFIRM,
                    true,
                )
            ) {
                $canApplyMsrp = true;
                break;
            }
        }

        $address->setCanApplyMsrp($canApplyMsrp);

        return $this;
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend_Tax extends Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend
{
    /**
     * Fetch totals
     *
     * @return $this
     */
    #[\Override]
    public function fetchTotals(Mage_Sales_Model_Quote_Address $address)
    {
        $amount = $address->getTaxAmount();
        if ($amount != 0) {
            $address->addTotal([
                'code'  => 'tax',
                'title' => Mage::helper('sales')->__('Tax'),
                'value' => $amount,
            ]);
        }
        return $this;
    }
}

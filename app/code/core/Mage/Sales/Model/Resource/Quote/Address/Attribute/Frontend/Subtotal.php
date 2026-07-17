<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend_Subtotal extends Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend
{
    /**
     * Add total
     *
     * @return $this
     */
    #[\Override]
    public function fetchTotals(Mage_Sales_Model_Quote_Address $address)
    {
        $address->addTotal([
            'code'  => 'subtotal',
            'title' => Mage::helper('sales')->__('Subtotal'),
            'value' => $address->getSubtotal(),
        ]);

        return $this;
    }
}

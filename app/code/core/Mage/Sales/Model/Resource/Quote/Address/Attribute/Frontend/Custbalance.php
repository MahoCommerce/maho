<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend_Custbalance extends Mage_Sales_Model_Resource_Quote_Address_Attribute_Frontend
{
    /**
     * Fetch customer balance
     *
     * @return $this
     */
    #[\Override]
    public function fetchTotals(Mage_Sales_Model_Quote_Address $address)
    {
        $custbalance = $address->getCustbalanceAmount();
        if ($custbalance != 0) {
            $address->addTotal([
                'code'  => 'custbalance',
                'title' => Mage::helper('sales')->__('Store Credit'),
                'value' => -$custbalance,
            ]);
        }
        return $this;
    }
}

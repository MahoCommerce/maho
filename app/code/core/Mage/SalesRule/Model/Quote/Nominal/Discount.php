<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

declare(strict_types=1);

class Mage_SalesRule_Model_Quote_Nominal_Discount extends Mage_SalesRule_Model_Quote_Discount
{
    /**
     * Don't add amounts to address
     *
     * @var bool
     */
    protected $_canAddAmountToAddress = false;

    /**
     * Don't fetch anything
     *
     * @return array
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        return Mage_Sales_Model_Quote_Address_Total_Abstract::fetch($address);
    }

    /**
     * Get nominal items only
     *
     * @return array
     */
    #[\Override]
    protected function _getAddressItems(Mage_Sales_Model_Quote_Address $address)
    {
        return $address->getAllNominalItems();
    }
}

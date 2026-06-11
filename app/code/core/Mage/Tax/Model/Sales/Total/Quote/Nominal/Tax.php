<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

class Mage_Tax_Model_Sales_Total_Quote_Nominal_Tax extends Mage_Tax_Model_Sales_Total_Quote_Tax
{
    /**
     * Don't add amounts to address
     *
     * @var bool
     */
    protected $_canAddAmountToAddress = false;

    /**
     * Custom row total key
     *
     * @var string
     */
    protected $_itemRowTotalKey = 'tax_amount';

    /**
     * Don't fetch anything
     *
     * @return array|Mage_Sales_Model_Quote_Address_Total_Abstract
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

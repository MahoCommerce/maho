<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Resource_Quote_Address_Attribute_Backend extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Collect totals
     *
     * @return $this
     */
    public function collectTotals(Mage_Sales_Model_Quote_Address $address)
    {
        return $this;
    }
}

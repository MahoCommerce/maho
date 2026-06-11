<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

class Mage_Downloadable_Model_CatalogIndex_Data_Downloadable extends Mage_CatalogIndex_Model_Data_Virtual
{
    /**
     * Retrieve product type code
     *
     * @return string
     */
    #[\Override]
    public function getTypeCode()
    {
        return Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE;
    }
}

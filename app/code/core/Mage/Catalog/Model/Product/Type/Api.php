<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Product_Type_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve product type list
     *
     * @return array
     */
    public function items()
    {
        $result = [];

        foreach (Mage_Catalog_Model_Product_Type::getOptionArray() as $type => $label) {
            $result[] = [
                'type'  => $type,
                'label' => $label,
            ];
        }

        return $result;
    }
}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Bundle
 */

declare(strict_types=1);

class Mage_Bundle_Model_CatalogIndex_Data_Bundle extends Mage_CatalogIndex_Model_Data_Simple
{
    /**
     * Defines when product type has children
     *
     * @var array<int, bool>|false
     */
    #[\Override]
    protected $_haveChildren = [
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_TIERS => false,
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_PRICES => false,
        Mage_CatalogIndex_Model_Retreiver::CHILDREN_FOR_ATTRIBUTES => true,
    ];

    #[\Override]
    protected $_haveParents = false;

    /**
     * Retrieve product type code
     *
     * @return string
     */
    #[\Override]
    public function getTypeCode()
    {
        return Mage_Catalog_Model_Product_Type::TYPE_BUNDLE;
    }

    /**
     * Get child link table and field settings
     *
     * @return array
     */
    #[\Override]
    protected function _getLinkSettings()
    {
        return [
            'table' => 'bundle/selection',
            'parent_field' => 'parent_product_id',
            'child_field' => 'product_id',
        ];
    }
}

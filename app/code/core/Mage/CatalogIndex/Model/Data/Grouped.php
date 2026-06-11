<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogIndex
 */

declare(strict_types=1);

class Mage_CatalogIndex_Model_Data_Grouped extends Mage_CatalogIndex_Model_Data_Abstract
{
    /**
     * Defines when product type has parents
     *
     * @var bool
     */
    protected $_haveParents = false;

    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogindex/data_grouped');
    }

    /**
     * Fetch final price for product
     *
     * @param array $product
     * @param Mage_Core_Model_Store $store
     * @param Mage_Customer_Model_Group $group
     * @return false
     */
    #[\Override]
    public function getFinalPrice($product, $store, $group)
    {
        return false;
    }

    /**
     * Retrieve product type code
     *
     * @return string
     */
    #[\Override]
    public function getTypeCode()
    {
        return Mage_Catalog_Model_Product_Type::TYPE_GROUPED;
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
            'table' => 'catalog/product_link',
            'parent_field' => 'product_id',
            'child_field' => 'linked_product_id',
            'additional' => ['link_type_id' => Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED],
        ];
    }
}

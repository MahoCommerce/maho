<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

interface Mage_Catalog_Helper_Category_Url_Rewrite_Interface
{
    /**
     * Join url rewrite table to eav collection
     *
     * @param int $storeId
     * @return Mage_Catalog_Helper_Category_Url_Rewrite
     */
    public function joinTableToEavCollection(Mage_Eav_Model_Entity_Collection_Abstract $collection, $storeId);

    /**
     * Join url rewrite table to flat collection
     *
     * @param int $storeId
     * @return Mage_Catalog_Helper_Category_Url_Rewrite_Interface
     */
    public function joinTableToCollection(Mage_Catalog_Model_Resource_Category_Flat_Collection $collection, $storeId);

    /**
     * Join url rewrite to select
     *
     * @param int $storeId
     * @return Mage_Catalog_Helper_Category_Url_Rewrite
     */
    public function joinTableToSelect(\Maho\Db\Select $select, $storeId);
}

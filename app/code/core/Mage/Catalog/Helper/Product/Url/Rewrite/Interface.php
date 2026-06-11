<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

interface Mage_Catalog_Helper_Product_Url_Rewrite_Interface
{
    /**
     * Prepare and return select
     *
     * @param int $categoryId
     * @param int $storeId
     * @return Maho\Db\Select
     */
    public function getTableSelect(array $productIds, $categoryId, $storeId);

    /**
     * Prepare url rewrite left join statement for given select instance and store_id parameter.
     *
     * @param int $storeId
     * @return Mage_Catalog_Helper_Product_Url_Rewrite_Interface
     */
    public function joinTableToSelect(\Maho\Db\Select $select, $storeId);
}

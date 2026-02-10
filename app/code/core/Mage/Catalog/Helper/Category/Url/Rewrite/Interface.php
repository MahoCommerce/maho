<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
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

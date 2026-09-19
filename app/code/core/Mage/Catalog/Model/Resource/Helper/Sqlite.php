<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Resource_Helper_Sqlite extends Mage_Eav_Model_Resource_Helper_Sqlite
{
    /**
     * Returns columns for select
     *
     * @param string $tableAlias
     * @param string $eavType
     * @return string
     */
    #[\Override]
    public function attributeSelectFields($tableAlias, $eavType)
    {
        return '*';
    }

    /**
     * Getting condition isNull(f1,f2) IS NOT Null
     *
     * @param string $field1
     * @param string $field2
     * @return string
     */
    public function getIsNullNotNullCondition($field1, $field2)
    {
        return sprintf('%s IS NOT NULL', $this->_getReadAdapter()->getIfNullSql($field1, $field2));
    }
}

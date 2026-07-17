<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

/**
 * Class Mage_Catalog_Model_Product_Condition
 *
 * @package    Mage_Catalog
 *
 * @method string getTable()
 * @method $this setTable(string $tableName)
 * @method string getPkFieldName()
 * @method $this setPkFieldName(string $fieldName)
 */
class Mage_Catalog_Model_Product_Condition extends \Maho\DataObject implements Mage_Catalog_Model_Product_Condition_Interface
{
    /**
     * @param Mage_Catalog_Model_Resource_Product_Collection $collection
     * @return $this
     */
    #[\Override]
    public function applyToCollection($collection)
    {
        if ($this->getTable() && $this->getPkFieldName()) {
            $collection->joinTable(
                $this->getTable(),
                $this->getPkFieldName() . '=entity_id',
                ['affected_product_id' => $this->getPkFieldName()],
            );
        }
        return $this;
    }

    /**
     * @param Maho\Db\Adapter\Pdo\Mysql $dbAdapter
     * @return string|Maho\Db\Select
     */
    #[\Override]
    public function getIdsSelect($dbAdapter)
    {
        if ($this->getTable() && $this->getPkFieldName()) {
            return $dbAdapter->select()
                ->from($this->getTable(), $this->getPkFieldName());
        }
        return '';
    }
}

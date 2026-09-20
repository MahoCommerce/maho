<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Entity_Attribute_Option extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/attribute_option', 'option_id');
    }

    /**
     * Add Join with option value for collection select
     *
     * @param Mage_Eav_Model_Entity_Collection_Abstract $collection
     * @param Mage_Eav_Model_Entity_Attribute $attribute
     * @param Maho\Db\Expr $valueExpr
     * @return $this
     */
    public function addOptionValueToCollection($collection, $attribute, $valueExpr)
    {
        $adapter        = $this->_getReadAdapter();
        $attributeCode  = $attribute->getAttributeCode();
        $optionTable1   = $attributeCode . '_option_value_t1';
        $optionTable2   = $attributeCode . '_option_value_t2';
        $tableJoinCond1 = "{$optionTable1}.option_id={$valueExpr} AND {$optionTable1}.store_id=0"
        ;
        $tableJoinCond2 = $adapter->quoteInto(
            "{$optionTable2}.option_id={$valueExpr} AND {$optionTable2}.store_id=?",
            $collection->getStoreId(),
        );
        $valueExpr      = $adapter->getCheckSql(
            "{$optionTable2}.value_id IS NULL",
            "{$optionTable1}.value",
            "{$optionTable2}.value",
        );

        $collection->getSelect()
            ->joinLeft(
                [$optionTable1 => $this->getTable('eav/attribute_option_value')],
                $tableJoinCond1,
                [],
            )
            ->joinLeft(
                [$optionTable2 => $this->getTable('eav/attribute_option_value')],
                $tableJoinCond2,
                [$attributeCode => $valueExpr],
            );

        return $this;
    }
}

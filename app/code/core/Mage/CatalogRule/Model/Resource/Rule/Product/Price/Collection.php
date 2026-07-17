<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

class Mage_CatalogRule_Model_Resource_Rule_Product_Price_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('catalogrule/rule_product_price');
    }

    /**
     * @return array
     */
    public function getProductIds()
    {
        $idsSelect = clone $this->getSelect();
        $idsSelect->reset(Maho\Db\Select::ORDER);
        $idsSelect->reset(Maho\Db\Select::LIMIT_COUNT);
        $idsSelect->reset(Maho\Db\Select::LIMIT_OFFSET);
        $idsSelect->reset(Maho\Db\Select::COLUMNS);
        $idsSelect->columns('main_table.product_id');
        $idsSelect->distinct(true);
        return $this->getConnection()->fetchCol($idsSelect);
    }
}

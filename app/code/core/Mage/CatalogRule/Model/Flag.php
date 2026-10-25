<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

class Mage_CatalogRule_Model_Flag extends Mage_Core_Model_Flag
{
    /**
     * Flag code
     *
     * @var string
     */
    protected $_flagCode = 'catalog_rules_dirty';

    /**
     * Return true when the rules changed after the last apply. With no rule and no rule price,
     * an apply changes nothing, so the flag goes back to 0.
     */
    public function hasChangesToApply(): bool
    {
        if (!$this->getState()) {
            return false;
        }

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');
        foreach (['catalogrule/rule', 'catalogrule/rule_product_price'] as $table) {
            $select = $adapter->select()->from($resource->getTableName($table), [new Maho\Db\Expr('1')])->limit(1);
            if ($adapter->fetchOne($select)) {
                return true;
            }
        }

        $this->setState(0)->save();
        return false;
    }
}

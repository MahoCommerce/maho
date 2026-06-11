<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

class Mage_SalesRule_Model_Resource_Report_Rule extends Mage_Reports_Model_Resource_Report_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_setResource('salesrule');
    }

    /**
     * Aggregate Coupons data
     *
     * @param mixed $from
     * @param mixed $to
     * @return $this
     */
    public function aggregate($from = null, $to = null)
    {
        Mage::getResourceModel('salesrule/report_rule_createdat')->aggregate($from, $to);
        Mage::getResourceModel('salesrule/report_rule_updatedat')->aggregate($from, $to);
        $this->_setFlagData(Mage_Reports_Model_Flag::REPORT_COUPONS_FLAG_CODE);

        return $this;
    }

    /**
     * Get all unique Rule Names from aggregated coupons usage data
     *
     * @return array
     */
    public function getUniqRulesNamesList()
    {
        $adapter = $this->_getReadAdapter();
        $tableName = $this->getTable('salesrule/coupon_aggregated');
        $select = $adapter->select()
            ->from(
                $tableName,
                new Maho\Db\Expr('DISTINCT rule_name'),
            )
            ->where('rule_name IS NOT NULL')
            ->where('rule_name <> ""')
            ->order('rule_name ASC');

        $rulesNames = $adapter->fetchAll($select);

        $result = [];

        foreach ($rulesNames as $row) {
            $result[] = $row['rule_name'];
        }

        return $result;
    }

}

<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_SalesRule
 */

class Mage_SalesRule_Model_Resource_Rule_Customer extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('salesrule/rule_customer', 'rule_customer_id');
    }

    /**
     * Get rule usage record for a customer
     *
     * @param Mage_SalesRule_Model_Rule_Customer $rule
     * @param int $customerId
     * @param int $ruleId
     * @return $this
     */
    public function loadByCustomerRule($rule, $customerId, $ruleId)
    {
        $read = $this->_getReadAdapter();
        $select = $read->select()->from($this->getMainTable())
            ->where('customer_id = :customer_id')
            ->where('rule_id = :rule_id');
        $data = $read->fetchRow($select, [':rule_id' => $ruleId, ':customer_id' => $customerId]);
        if ($data === false) {
            // set empty data, as an existing rule object might be used
            $data = [];
        }
        $rule->setData($data);
        return $this;
    }

    /**
     * Count one more use of the rule by the customer, refusing it once
     * uses_per_customer is reached. The counter moves in one conditional
     * UPDATE; the table has no unique key, so the first use locks the row it
     * looks for and two placements cannot both insert one.
     *
     * @param int $usesPerCustomer 0 for unlimited
     * @throws Mage_Core_Exception when the limit is reached
     */
    public function incrementTimesUsed(int $customerId, int $ruleId, int $usesPerCustomer = 0): void
    {
        $adapter = $this->_getWriteAdapter();
        $where = [
            'rule_id = ?' => $ruleId,
            'customer_id = ?' => $customerId,
        ];
        if ($usesPerCustomer > 0) {
            $where['times_used < ?'] = $usesPerCustomer;
        }

        $updated = $adapter->update(
            $this->getMainTable(),
            ['times_used' => new Maho\Db\Expr('times_used + 1')],
            $where,
        );
        if ($updated > 0) {
            return;
        }

        $select = $adapter->select()
            ->from($this->getMainTable(), [$this->getIdFieldName()])
            ->where('rule_id = ?', $ruleId)
            ->where('customer_id = ?', $customerId)
            ->forUpdate(true);
        if ($adapter->fetchOne($select) !== false) {
            Mage::throwException(Mage::helper('salesrule')->__('You have reached the usage limit for this promotion.'));
        }

        $adapter->insert($this->getMainTable(), [
            'rule_id' => $ruleId,
            'customer_id' => $customerId,
            'times_used' => 1,
        ]);
    }

    public function decrementTimesUsed(int $customerId, int $ruleId): void
    {
        $this->_getWriteAdapter()->update(
            $this->getMainTable(),
            ['times_used' => new Maho\Db\Expr('times_used - 1')],
            [
                'rule_id = ?' => $ruleId,
                'customer_id = ?' => $customerId,
                'times_used > 0',
            ],
        );
    }
}

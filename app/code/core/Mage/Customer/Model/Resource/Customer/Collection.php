<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

/**
 * Customers collection
 *
 * @package    Mage_Customer
 *
 * @method Mage_Customer_Model_Customer[] getItems()
 */
class Mage_Customer_Model_Resource_Customer_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/customer');
    }

    /**
     * Group result by customer email
     *
     * @return $this
     */
    public function groupByEmail()
    {
        // The dedup subquery is built in _renderFiltersBefore() (i.e. at load/count
        // time), so it sees every filter applied to the collection: the per-email
        // representative and email_count are computed within the filtered set, not
        // over the whole customer_entity table.
        $this->setFlag('group_by_email', true);

        return $this;
    }

    /**
     * Apply the deferred groupByEmail() dedup once all filters are on the select.
     *
     * Joins a grouped derived table instead of grouping the main select, so that
     * e.* and EAV attribute loading stay valid under strict GROUP BY. The derived
     * table is a filtered clone of the main select grouped by email, so the
     * MIN(entity_id) representative row always satisfies the collection's filters
     * and email_count only counts rows matching them.
     */
    #[\Override]
    protected function _renderFiltersBefore(): void
    {
        if (!$this->getFlag('group_by_email') || $this->getFlag('group_by_email_applied')) {
            return;
        }
        $this->setFlag('group_by_email_applied', true);

        $subSelect = clone $this->getSelect();
        $subSelect->reset(Maho\Db\Select::COLUMNS)
            ->reset(Maho\Db\Select::ORDER)
            ->reset(Maho\Db\Select::GROUP)
            ->reset(Maho\Db\Select::LIMIT_COUNT)
            ->reset(Maho\Db\Select::LIMIT_OFFSET)
            ->columns([
                'entity_id' => new Maho\Db\Expr('MIN(e.entity_id)'),
                // DISTINCT keeps the count exact even when a filter join fans out rows
                'email_count' => new Maho\Db\Expr('COUNT(DISTINCT e.entity_id)'),
            ])
            ->group('e.email');

        $this->getSelect()->joinInner(
            ['email' => $subSelect],
            'email.entity_id = e.entity_id',
            ['email_count'],
        );
    }

    /**
     * Add Name to select
     *
     * @return $this
     */
    public function addNameToSelect()
    {
        $fields = [];
        $customerAccount = Mage::getConfig()->getFieldset('customer_account');
        foreach ($customerAccount as $code => $node) {
            if ($node->is('name')) {
                $fields[$code] = $code;
            }
        }

        $adapter = $this->getConnection();
        $concatenate = [];
        if (isset($fields['prefix'])) {
            $concatenate[] = $adapter->getCheckSql(
                '{{prefix}} IS NOT NULL AND {{prefix}} != \'\'',
                $adapter->getConcatSql(['LTRIM(RTRIM({{prefix}}))', '\' \'']),
                '\'\'',
            );
        }
        $concatenate[] = 'LTRIM(RTRIM({{firstname}}))';
        $concatenate[] = '\' \'';
        if (isset($fields['middlename'])) {
            $concatenate[] = $adapter->getCheckSql(
                '{{middlename}} IS NOT NULL AND {{middlename}} != \'\'',
                $adapter->getConcatSql(['LTRIM(RTRIM({{middlename}}))', '\' \'']),
                '\'\'',
            );
        }
        $concatenate[] = 'LTRIM(RTRIM({{lastname}}))';
        if (isset($fields['suffix'])) {
            $concatenate[] = $adapter
                    ->getCheckSql(
                        '{{suffix}} IS NOT NULL AND {{suffix}} != \'\'',
                        $adapter->getConcatSql(['\' \'', 'LTRIM(RTRIM({{suffix}}))']),
                        '\'\'',
                    );
        }

        $nameExpr = $adapter->getConcatSql($concatenate);

        $this->addExpressionAttributeToSelect('name', $nameExpr, $fields);

        return $this;
    }

    /**
     * Get SQL for get record count
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        $select = parent::getSelectCountSql();
        $select->resetJoinLeft();

        return $select;
    }

    /**
     * Reset left join
     *
     * @param int $limit
     * @param int $offset
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _getAllIdsSelect($limit = null, $offset = null)
    {
        $idsSelect = parent::_getAllIdsSelect($limit, $offset);
        $idsSelect->resetJoinLeft();
        return $idsSelect;
    }
}

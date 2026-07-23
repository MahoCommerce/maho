<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

declare(strict_types=1);

class Maho_AdminActivityLog_Model_Resource_Activity_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('adminactivitylog/activity');
    }

    /**
     * Collapse the collection to one row per action group (the latest activity of
     * each group becomes the representative row, with an activity_count column).
     *
     * The dedup subquery is built in _renderFiltersBefore() so it sees every filter
     * applied to the collection (e.g. admin grid filters): representatives and
     * counts are computed within the filtered set, matching the behaviour of the
     * previous outer GROUP BY while staying legal under strict GROUP BY.
     */
    public function addActionGroupDedup(): self
    {
        $this->setFlag('dedup_action_groups', true);

        return $this;
    }

    #[\Override]
    protected function _renderFiltersBefore(): void
    {
        if (!$this->getFlag('dedup_action_groups') || $this->getFlag('dedup_action_groups_applied')) {
            return;
        }
        $this->setFlag('dedup_action_groups_applied', true);

        // Grouping happens only inside the subquery, so the outer main_table.*
        // select stays valid under strict GROUP BY (ONLY_FULL_GROUP_BY /
        // PostgreSQL). The CASE expression keeps each row without an
        // action_group_id in its own group, avoiding a string/integer COALESCE.
        $subSelect = clone $this->getSelect();
        $subSelect->reset(Maho\Db\Select::COLUMNS)
            ->reset(Maho\Db\Select::ORDER)
            ->reset(Maho\Db\Select::LIMIT_COUNT)
            ->reset(Maho\Db\Select::LIMIT_OFFSET)
            ->columns([
                'representative_id' => new Maho\Db\Expr('MAX(main_table.activity_id)'),
                'activity_count' => new Maho\Db\Expr('COUNT(*)'),
            ])
            ->group('main_table.action_group_id')
            ->group(new Maho\Db\Expr('CASE WHEN main_table.action_group_id IS NULL THEN main_table.activity_id END'));

        $this->getSelect()->join(
            ['grp' => $subSelect],
            'main_table.activity_id = grp.representative_id',
            ['activity_count'],
        );
    }
}

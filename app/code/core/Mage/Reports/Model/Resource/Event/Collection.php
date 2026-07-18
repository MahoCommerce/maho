<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

/**
 * @method Mage_Reports_Model_Resource_Event _getResource()
 * @method Mage_Reports_Model_Resource_Event getResource()
 */
class Mage_Reports_Model_Resource_Event_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Store Ids
     *
     * @var array
     */
    protected $_storeIds;

    /**
     * Use analytic function flag
     * If true - allows to prepare final select with analytic function
     *
     * @var bool
     */
    protected $_useAnalyticFunction         = true;

    #[\Override]
    protected function _construct()
    {
        $this->_init('reports/event');
    }

    /**
     * Add store ids filter
     *
     * @return $this
     */
    public function addStoreFilter(array $storeIds)
    {
        $this->_storeIds = $storeIds;
        return $this;
    }

    /**
     * Add recently filter
     *
     * @param int $typeId
     * @param int $subjectId
     * @param int $subtype
     * @param int|array $ignore
     * @param int $limit
     * @return $this
     */
    public function addRecentlyFiler($typeId, $subjectId, $subtype = 0, $ignore = null, $limit = 15)
    {
        $stores = $this->getResource()->getCurrentStoreIds($this->_storeIds);
        $select = $this->getSelect();

        // Reset to only the columns needed; grouping by object_id deduplicates per
        // product/entity.  MAX(event_id) picks the most-recent event per object.
        // Without this reset, the default main_table.* would contain non-aggregated
        // columns (event_id, store_id, …) which violates ONLY_FULL_GROUP_BY.
        $select->reset(Maho\Db\Select::COLUMNS)
            ->columns([
                'object_id' => 'main_table.object_id',
                'event_id'  => new Maho\Db\Expr('MAX(main_table.event_id)'),
            ]);

        $select->where('event_type_id = ?', $typeId)
            ->where('subject_id = ?', $subjectId)
            ->where('subtype = ?', $subtype)
            ->where('store_id IN(?)', $stores);
        if ($ignore) {
            if (is_array($ignore)) {
                $select->where('object_id NOT IN(?)', $ignore);
            } else {
                $select->where('object_id <> ?', $ignore);
            }
        }
        $select->group('object_id')
            ->limit($limit);
        return $this;
    }
}

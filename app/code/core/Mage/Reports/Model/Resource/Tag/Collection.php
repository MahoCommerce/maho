<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_Tag_Collection extends Mage_Tag_Model_Resource_Popular_Collection
{
    /**
     * Add tag popularity to select by specified store ids
     *
     * @param int|array $storeIds
     * @return $this
     */
    public function addPopularity($storeIds)
    {
        $select = $this->getSelect()
            ->joinLeft(
                ['tr' => $this->getTable('tag/relation')],
                'main_table.tag_id = tr.tag_id AND tr.active = 1',
                ['popularity' => 'COUNT(tr.tag_id)'],
            );
        if (!empty($storeIds)) {
            $select->where('tr.store_id IN(?)', $storeIds);
        }

        $select->group('main_table.tag_id');

        /**
         * Allow to use analytic function
         */
        $this->_useAnalyticFunction = true;

        return $this;
    }
}

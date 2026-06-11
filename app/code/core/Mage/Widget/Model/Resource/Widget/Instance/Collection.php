<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Widget
 */

declare(strict_types=1);

class Mage_Widget_Model_Resource_Widget_Instance_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Fields map for correlation names & real selected fields
     *
     * @var array|null
     */
    protected $_map = ['fields' => ['type' => 'instance_type']];

    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_init('widget/widget_instance');
    }

    /**
     * Filter by store ids
     *
     * @param array|int $storeIds
     * @param bool $withDefaultStore if TRUE also filter by store id '0'
     * @return $this
     */
    public function addStoreFilter($storeIds = [], $withDefaultStore = true)
    {
        if (!is_array($storeIds)) {
            $storeIds = [$storeIds];
        }
        if ($withDefaultStore && !in_array('0', $storeIds)) {
            array_unshift($storeIds, 0);
        }
        $where = [];
        foreach ($storeIds as $storeId) {
            $where[] = $this->_getConditionSql('store_ids', ['finset' => $storeId]);
        }

        $this->_select->where(implode(' OR ', $where));

        return $this;
    }
}

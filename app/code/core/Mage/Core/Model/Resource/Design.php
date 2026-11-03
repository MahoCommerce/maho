<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Design extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/design_change', 'design_change_id');
    }

    /**
     * Return whether another change of $storeId applies on a day between $dateFrom and $dateTo.
     * A null date is an open bound. The change $excludeId does not count.
     */
    public function hasIntersection(int $storeId, ?string $dateFrom, ?string $dateTo, int $excludeId): bool
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from(['main_table' => $this->getTable('design_change')])
            ->where('main_table.store_id = :store_id')
            ->where('main_table.design_change_id <> :current_id');

        $dateConditions = ['date_to IS NULL AND date_from IS NULL'];

        if (!empty($dateFrom)) {
            $dateConditions[] = ':date_from BETWEEN date_from AND date_to';
            $dateConditions[] = ':date_from >= date_from and date_to IS NULL';
            $dateConditions[] = ':date_from <= date_to and date_from IS NULL';
        } else {
            $dateConditions[] = 'date_from IS NULL';
        }

        if (!empty($dateTo)) {
            $dateConditions[] = ':date_to BETWEEN date_from AND date_to';
            $dateConditions[] = ':date_to >= date_from AND date_to IS NULL';
            $dateConditions[] = ':date_to <= date_to AND date_from IS NULL';
        } else {
            $dateConditions[] = 'date_to IS NULL';
        }

        if (empty($dateFrom) && !empty($dateTo)) {
            $dateConditions[] = 'date_to <= :date_to OR date_from <= :date_to';
        }

        if (!empty($dateFrom) && empty($dateTo)) {
            $dateConditions[] = 'date_to >= :date_from OR date_from >= :date_from';
        }

        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateConditions[] = 'date_from BETWEEN :date_from AND :date_to';
            $dateConditions[] = 'date_to BETWEEN :date_from AND :date_to';
        } elseif (empty($dateFrom) && empty($dateTo)) {
            $dateConditions = [];
        }

        if (!empty($dateConditions)) {
            $condition = '(' . implode(') OR (', $dateConditions) . ')';
            $select->where($condition);
        }

        $bind = [
            'store_id'   => $storeId,
            'current_id' => $excludeId,
        ];

        if (!empty($dateTo)) {
            $bind['date_to'] = $dateTo;
        }
        if (!empty($dateFrom)) {
            $bind['date_from'] = $dateFrom;
        }

        return (bool) $adapter->fetchOne($select, $bind);
    }

    /**
     * Load changes for specific store and date
     *
     * @param int $storeId
     * @param string $date
     * @return array
     */
    public function loadChange($storeId, $date = null)
    {
        $date ??= Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

        $select = $this->_getReadAdapter()->select()
            ->from(['main_table' => $this->getTable('design_change')])
            ->where('store_id = :store_id')
            ->where('date_from <= :required_date or date_from IS NULL')
            ->where('date_to >= :required_date or date_to IS NULL');

        $bind = [
            'store_id'      => (int) $storeId,
            'required_date' => $date,
        ];

        return $this->_getReadAdapter()->fetchRow($select, $bind);
    }
}

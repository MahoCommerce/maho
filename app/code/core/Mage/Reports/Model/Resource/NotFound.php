<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Resource_NotFound extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('reports/not_found', 'not_found_id');
    }

    /**
     * Add one hit to $requestPath in $storeId, or create its row. A null $referer keeps the last referer.
     */
    public function recordHit(int $storeId, string $requestPath, ?string $referer): void
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getMainTable();
        $now = Mage::app()->getLocale()->formatDateForDb('now');

        $update = [
            'hits' => new \Maho\Db\Expr($adapter->quoteIdentifier($table) . '.' . $adapter->quoteIdentifier('hits') . ' + 1'),
            'last_seen_at',
        ];
        if ($referer !== null) {
            $update[] = 'referer';
        }

        $adapter->insertOnDuplicate($table, [
            'store_id' => $storeId,
            'request_path' => $requestPath,
            'hits' => 1,
            'referer' => $referer,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ], $update);
    }

    /**
     * Delete the rows not seen in the last $daysToKeep days, then keep only the $maxRows rows seen last.
     * 0 turns off each limit.
     */
    public function clean(int $daysToKeep, int $maxRows): int
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getMainTable();
        $deleted = 0;

        if ($daysToKeep > 0) {
            $cutoff = Mage::app()->getLocale()->formatDateForDb("-{$daysToKeep} days");
            $deleted += $adapter->delete($table, ['last_seen_at < ?' => $cutoff]);
        }

        if ($maxRows > 0) {
            $oldestKept = $adapter->fetchRow(
                $adapter->select()
                    ->from($table, ['not_found_id', 'last_seen_at'])
                    ->order(['last_seen_at DESC', 'not_found_id DESC'])
                    ->limit(1, $maxRows - 1),
            );
            if ($oldestKept) {
                $deleted += $adapter->delete(
                    $table,
                    $adapter->quoteInto('last_seen_at < ?', $oldestKept['last_seen_at'])
                    . ' OR (' . $adapter->quoteInto('last_seen_at = ?', $oldestKept['last_seen_at'])
                    . ' AND ' . $adapter->quoteInto('not_found_id < ?', (int) $oldestKept['not_found_id']) . ')',
                );
            }
        }

        return $deleted;
    }

    /**
     * @param list<int> $ids
     */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        return $this->_getWriteAdapter()->delete($this->getMainTable(), ['not_found_id IN (?)' => $ids]);
    }
}

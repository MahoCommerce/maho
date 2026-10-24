<?php

/**
 * Reads the HTML that a #[Maho\Config\TailwindSource] method returns from its table.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Model_Resource_Design_Content extends Mage_Core_Model_Resource_Db_Abstract
{
    public const PAGE_SIZE = 1000;

    #[\Override]
    protected function _construct(): void
    {
        $this->_setResource('core');
    }

    /**
     * Each non-empty value of $columns in $table that contains a class attribute. $table is the
     * real table name, such as the result of getMainTable(). The rows are read in pages of
     * PAGE_SIZE over $idColumn, so a large table does not fill the memory.
     *
     * @param list<string> $columns
     * @param array<string, scalar|list<scalar>> $filters column => value, or column => list of values
     * @return Generator<int, string>
     */
    public function readColumns(string $table, string $idColumn, array $columns, array $filters = []): Generator
    {
        $adapter = $this->_getReadAdapter();
        $lastId = null;

        do {
            $select = $adapter->select()
                ->from($table, [$idColumn, ...$columns])
                ->order($idColumn . ' ASC')
                ->limit(self::PAGE_SIZE);
            $conditions = array_map(
                fn(string $column): string => $adapter->prepareSqlCondition($adapter->quoteIdentifier($column), ['like' => '%class%']),
                $columns,
            );
            $select->where(implode(' OR ', $conditions));
            foreach ($filters as $column => $value) {
                $select->where($adapter->quoteIdentifier($column) . (is_array($value) ? ' IN (?)' : ' = ?'), $value);
            }
            if ($lastId !== null) {
                $select->where($adapter->quoteIdentifier($idColumn) . ' > ?', $lastId);
            }

            $rows = $adapter->fetchAll($select);
            foreach ($rows as $row) {
                $lastId = $row[$idColumn];
                foreach ($columns as $column) {
                    if (is_string($row[$column]) && $row[$column] !== '') {
                        yield $row[$column];
                    }
                }
            }
        } while (count($rows) === self::PAGE_SIZE);
    }
}

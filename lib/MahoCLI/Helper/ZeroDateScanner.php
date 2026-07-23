<?php

/**
 * Scans MySQL/MariaDB schemas for legacy zero dates that strict SQL_MODE rejects.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Maho\Db\Adapter\Pdo\Mysql;

class ZeroDateScanner
{
    /**
     * Columns whose DEFAULT is a zero date: any INSERT omitting the column fails
     * under NO_ZERO_DATE.
     *
     * @return list<array{table: string, column: string, nullable: bool}>
     */
    public static function findZeroDateDefaults(Mysql $adapter): array
    {
        $rows = $adapter->fetchAll(
            'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS'
            . ' WHERE TABLE_SCHEMA = ? AND COLUMN_DEFAULT LIKE ?',
            [self::databaseName($adapter), '0000-00-00%'],
        );

        $findings = [];
        foreach ($rows as $row) {
            $findings[] = [
                'table' => $row['TABLE_NAME'],
                'column' => $row['COLUMN_NAME'],
                'nullable' => $row['IS_NULLABLE'] === 'YES',
            ];
        }
        return $findings;
    }

    /**
     * Columns containing stored zero-date values: any UPDATE rewriting such a row
     * fails under NO_ZERO_DATE. Includes the per-column row count.
     *
     * @return list<array{table: string, column: string, nullable: bool, rows: int}>
     */
    public static function findZeroDateValues(Mysql $adapter): array
    {
        $columns = $adapter->fetchAll(
            'SELECT TABLE_NAME, COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS'
            . " WHERE TABLE_SCHEMA = ? AND DATA_TYPE IN ('date', 'datetime', 'timestamp')",
            [self::databaseName($adapter)],
        );

        $findings = [];
        foreach ($columns as $column) {
            $rows = (int) $adapter->fetchOne(sprintf(
                'SELECT COUNT(*) FROM %s WHERE %s',
                $adapter->quoteIdentifier($column['TABLE_NAME']),
                self::zeroDatePredicate($adapter, $column['COLUMN_NAME']),
            ));
            if ($rows > 0) {
                $findings[] = [
                    'table' => $column['TABLE_NAME'],
                    'column' => $column['COLUMN_NAME'],
                    'nullable' => $column['IS_NULLABLE'] === 'YES',
                    'rows' => $rows,
                ];
            }
        }
        return $findings;
    }

    /**
     * WHERE fragment matching zero dates (including partial ones like
     * '0000-00-00 10:30:00'). Compares as CHAR: under strict SQL_MODE,
     * comparing a date column to a zero-date literal is itself an error.
     */
    public static function zeroDatePredicate(Mysql $adapter, string $column): string
    {
        return sprintf(
            "CAST(%s AS CHAR) LIKE '0000-00-00%%'",
            $adapter->quoteIdentifier($column),
        );
    }

    private static function databaseName(Mysql $adapter): string
    {
        return (string) $adapter->fetchOne('SELECT DATABASE()');
    }
}

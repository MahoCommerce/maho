<?php

/**
 * Finds tables whose on-disk footprint is mostly reclaimable free space.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package MahoCLI
 */

declare(strict_types=1);

namespace MahoCLI\Helper;

use Maho\Db\Adapter\AdapterInterface;
use Maho\Db\Adapter\Pdo\Mysql;
use Maho\Db\Adapter\Pdo\Pgsql;
use Maho\Db\Adapter\Pdo\Sqlite;

class TableBloatScanner
{
    /** Below this, a rebuild costs more downtime than the space it returns. */
    public const MIN_TOTAL_BYTES = 50 * 1024 * 1024;

    /** Free share of a table below which fragmentation is ordinary churn, not bloat. */
    public const MIN_RECLAIMABLE_RATIO = 0.30;

    /**
     * Tables holding more reclaimable space than the thresholds allow, largest first.
     * The three backends measure different things, so `reclaimable` is an estimate
     * everywhere except MySQL: PostgreSQL infers it from the dead-tuple share, and
     * SQLite reports the whole database file rather than individual tables.
     *
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     */
    public static function scan(
        AdapterInterface $adapter,
        string $tablePrefix = '',
        int $minTotalBytes = self::MIN_TOTAL_BYTES,
        float $minRatio = self::MIN_RECLAIMABLE_RATIO,
    ): array {
        $candidates = match (true) {
            $adapter instanceof Mysql => self::mysqlCandidates($adapter, $tablePrefix),
            $adapter instanceof Pgsql => self::pgsqlCandidates($adapter, $tablePrefix),
            $adapter instanceof Sqlite => self::sqliteCandidates($adapter),
            default => [],
        };

        $findings = array_values(array_filter(
            $candidates,
            static fn(array $c): bool => $c['total'] >= $minTotalBytes && $c['ratio'] >= $minRatio,
        ));

        usort($findings, static fn(array $a, array $b): int => $b['reclaimable'] <=> $a['reclaimable']);

        return $findings;
    }

    /**
     * Why a rebuild would not hand space back to the filesystem on this server,
     * or null when it would. Reported alongside an empty scan so the absence of
     * findings is not mistaken for the absence of bloat.
     */
    public static function reclaimUnavailableReason(AdapterInterface $adapter): ?string
    {
        if (!($adapter instanceof Mysql) || self::mysqlFilePerTable($adapter)) {
            return null;
        }

        return 'innodb_file_per_table is OFF, so every table lives in the shared tablespace:'
            . ' a rebuild returns free space to that tablespace but never to the filesystem,'
            . ' and DATA_FREE cannot be attributed to individual tables.';
    }

    /**
     * The statements that reclaim the space. Every backend rebuilds the data in
     * place, so all of them are maintenance-window operations: MySQL's OPTIMIZE
     * TABLE is an online rebuild needing free disk equal to the table, PostgreSQL's
     * VACUUM FULL holds an ACCESS EXCLUSIVE lock for its duration, and SQLite's
     * VACUUM rewrites the entire database file.
     *
     * @param list<string> $tables
     * @return list<string>
     */
    public static function optimizeStatements(AdapterInterface $adapter, array $tables): array
    {
        if ($adapter instanceof Sqlite) {
            // SQLite has no per-table storage accounting; VACUUM is all or nothing.
            return $tables === [] ? [] : ['VACUUM'];
        }

        $template = $adapter instanceof Pgsql ? 'VACUUM (FULL, ANALYZE) %s' : 'OPTIMIZE TABLE %s';

        return array_map(
            static fn(string $table): string => sprintf($template, $adapter->quoteIdentifier($table)),
            array_values($tables),
        );
    }

    /**
     * MySQL returns a status result set from OPTIMIZE TABLE that has to be consumed
     * before the connection accepts another statement; VACUUM returns nothing.
     */
    public static function runStatement(AdapterInterface $adapter, string $sql): void
    {
        if (str_starts_with(strtoupper(ltrim($sql)), 'OPTIMIZE')) {
            $adapter->getConnection()->executeQuery($sql)->fetchAllAssociative();
            return;
        }

        $adapter->getConnection()->executeStatement($sql);
    }

    /**
     * Bytes the table occupies on disk including indexes and free space, so a
     * before/after difference is the space actually returned. Null when the table
     * is unknown to the server. SQLite reports the whole database file.
     */
    public static function totalSize(AdapterInterface $adapter, string $table): ?int
    {
        $size = match (true) {
            $adapter instanceof Mysql => $adapter->fetchOne(
                'SELECT DATA_LENGTH + INDEX_LENGTH + DATA_FREE FROM information_schema.TABLES'
                . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
                [$table],
            ),
            $adapter instanceof Pgsql => $adapter->fetchOne(
                'SELECT pg_total_relation_size(oid) FROM pg_class WHERE oid = to_regclass(?)',
                [$table],
            ),
            $adapter instanceof Sqlite => (int) $adapter->fetchOne('PRAGMA page_count')
                * (int) $adapter->fetchOne('PRAGMA page_size'),
            default => false,
        };

        return $size === false || $size === null ? null : (int) $size;
    }

    /**
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     */
    private static function mysqlCandidates(Mysql $adapter, string $tablePrefix): array
    {
        if (!self::mysqlFilePerTable($adapter)) {
            return [];
        }

        // Restricted to InnoDB: any other engine is already reported by the storage
        // engine check, and converting it rebuilds the table anyway.
        $rows = $adapter->fetchAll(
            'SELECT TABLE_NAME, DATA_LENGTH, INDEX_LENGTH, DATA_FREE FROM information_schema.TABLES'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE = 'InnoDB'",
        );

        $candidates = [];
        foreach (self::filterByPrefix($rows, 'TABLE_NAME', $tablePrefix) as $row) {
            // DATA_FREE is extent-granular, so a small table reads as heavily
            // fragmented on a single unused extent. MIN_TOTAL_BYTES filters those out.
            $free = (int) $row['DATA_FREE'];
            $total = (int) $row['DATA_LENGTH'] + (int) $row['INDEX_LENGTH'] + $free;
            $candidates[] = [
                'table' => (string) $row['TABLE_NAME'],
                'total' => $total,
                'reclaimable' => $free,
                'ratio' => $total > 0 ? $free / $total : 0.0,
                'detail' => '',
            ];
        }

        return $candidates;
    }

    /**
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     */
    private static function pgsqlCandidates(Pgsql $adapter, string $tablePrefix): array
    {
        // Dead tuples are what autovacuum normally reclaims for reuse; a large
        // standing share of them means it is not keeping up (or is blocked by a
        // long-running transaction or an abandoned replication slot).
        $rows = $adapter->fetchAll(
            'SELECT c.relname AS table_name, pg_total_relation_size(c.oid) AS total_bytes,'
            . ' s.n_live_tup, s.n_dead_tup, s.last_autovacuum, s.last_vacuum'
            . ' FROM pg_stat_user_tables s JOIN pg_class c ON c.oid = s.relid'
            . ' WHERE s.schemaname = current_schema()',
        );

        $candidates = [];
        foreach (self::filterByPrefix($rows, 'table_name', $tablePrefix) as $row) {
            $dead = (int) $row['n_dead_tup'];
            $tuples = (int) $row['n_live_tup'] + $dead;
            if ($tuples === 0) {
                continue;
            }

            $total = (int) $row['total_bytes'];
            $ratio = $dead / $tuples;
            $lastVacuum = $row['last_autovacuum'] ?? $row['last_vacuum'];
            $candidates[] = [
                'table' => (string) $row['table_name'],
                'total' => $total,
                // Estimated from the tuple counts, which are themselves statistics
                // estimates reset by pg_stat_reset(). Treat as an order of magnitude.
                'reclaimable' => (int) round($total * $ratio),
                'ratio' => $ratio,
                'detail' => sprintf(
                    '%s dead rows, last vacuum %s',
                    number_format($dead),
                    $lastVacuum === null ? 'never' : substr((string) $lastVacuum, 0, 19),
                ),
            ];
        }

        return $candidates;
    }

    /**
     * @return list<array{table: string, total: int, reclaimable: int, ratio: float, detail: string}>
     */
    private static function sqliteCandidates(Sqlite $adapter): array
    {
        $pageSize = (int) $adapter->fetchOne('PRAGMA page_size');
        $pageCount = (int) $adapter->fetchOne('PRAGMA page_count');
        $freelist = (int) $adapter->fetchOne('PRAGMA freelist_count');
        if ($pageCount === 0) {
            return [];
        }

        return [[
            'table' => self::sqliteDatabaseName($adapter),
            'total' => $pageCount * $pageSize,
            'reclaimable' => $freelist * $pageSize,
            'ratio' => $freelist / $pageCount,
            'detail' => sprintf('%s free page(s) of %s, whole database file', number_format($freelist), number_format($pageCount)),
        ]];
    }

    private static function sqliteDatabaseName(Sqlite $adapter): string
    {
        $rows = $adapter->fetchAll('PRAGMA database_list');
        foreach ($rows as $row) {
            if (($row['name'] ?? '') === 'main') {
                $file = (string) ($row['file'] ?? '');
                return $file === '' ? 'main' : basename($file);
            }
        }
        return 'main';
    }

    /**
     * A prefix means the database may be shared with another application, whose
     * tables are none of our business. Filtered here rather than with LIKE, whose
     * pattern would need escaping ('_' is a wildcard).
     *
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private static function filterByPrefix(array $rows, string $column, string $tablePrefix): array
    {
        if ($tablePrefix === '') {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => str_starts_with((string) $row[$column], $tablePrefix),
        ));
    }

    private static function mysqlFilePerTable(Mysql $adapter): bool
    {
        try {
            return in_array((string) $adapter->fetchOne('SELECT @@innodb_file_per_table'), ['1', 'ON'], true);
        } catch (\Exception) {
            // Variable absent: assume the modern default rather than suppress the check.
            return true;
        }
    }
}

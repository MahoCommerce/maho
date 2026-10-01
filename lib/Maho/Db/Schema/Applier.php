<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\DefaultExpression;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Maho\Db\Adapter\AdapterInterface;

final class Applier
{
    /**
     * Non-transactional engines, converted away from. An allowlist rather than
     * "anything but InnoDB": ARCHIVE, CSV, BLACKHOLE, FEDERATED and MRG_MyISAM
     * are deliberate choices a conversion would break.
     */
    private const LEGACY_ENGINES = ['MyISAM', 'MEMORY', 'HEAP', 'Aria', 'MARIA'];

    /**
     * Compute the SQL needed to reconcile the live database with the target Schema.
     *
     * The introspected current schema is scoped to only the tables declared in
     * $target, so unrelated existing tables are never considered for DROP.
     *
     * $tablePrefix scopes the storage-engine and charset passes, the parts of
     * the plan that look beyond the declared tables. Pass '' when the database
     * belongs to Maho alone, or $withTableConversions = false to drop those
     * passes entirely, for a caller asking only whether the *declared* schema
     * is behind (see Status::isConverged).
     *
     * @return list<string> SQL statements (empty when no changes are needed)
     */
    public static function plan(
        Connection $connection,
        Schema $target,
        string $tablePrefix = '',
        bool $withTableConversions = true,
    ): array {
        $schemaManager = $connection->createSchemaManager();
        $platform = $connection->getDatabasePlatform();

        // One round-trip to enumerate, then membership-test in PHP. A per-table
        // tablesExist() call would mean N introspection queries before the
        // comparator even runs (~200 on a fully-converted schema).
        // introspectTableNames() returns OptionallyQualifiedName objects. Key
        // the map on the unqualified identifier value, not toString(): on
        // SQLite, DBAL marks the introspected name as quoted, so toString()
        // re-emits it as '"core_resource"' (DBAL 4.4 bug), which would never
        // match the unquoted target name and queue every existing table for
        // CREATE. getValue() yields the raw 'core_resource' on every engine,
        // and dropping the qualifier is safe since all Maho tables share one
        // schema and targets are declared unqualified.
        $existing = [];
        foreach ($schemaManager->introspectTableNames() as $existingName) {
            $existing[$existingName->getUnqualifiedName()->getValue()] = true;
        }

        // Emitted here but executed ahead of the diff, so each live table is
        // also renamed in memory below and the Comparator sees the end state.
        $tableRenames = Renamer::planTableRenames($platform, $target, $existing);
        $columnRenames = [];

        $introspectedTables = [];
        $existingTables = [];
        $tablesToCreate = [];
        $tablesToAlter = [];
        foreach ($target->getTables() as $targetTable) {
            $name = $targetTable->getObjectName()->getUnqualifiedName()->getValue();
            // A table awaiting a rename is still stored under its former name.
            $liveName = $tableRenames['sources'][$name] ?? $name;
            if (isset($existing[$liveName])) {
                $liveTable = $schemaManager->introspectTableByUnquotedName($liveName);
                // A clone: reconcile() below strips the comments, and the charset pass must keep them.
                $introspectedTables[$liveName] = clone $liveTable;
                if ($liveName !== $name) {
                    $liveTable = $liveTable->edit()
                        ->setName($targetTable->getObjectName())
                        ->create();
                }
                $liveTable = Renamer::repointForeignKeys($liveTable, $tableRenames['sources']);

                $columnRename = Renamer::renameLiveColumns($platform, $liveTable, $targetTable);
                $columnRenames = array_merge($columnRenames, $columnRename['sql']);
                $liveTable = $columnRename['live'];
                // Reduce the live table and its declarative target to
                // parity-equivalent form, so the Comparator below emits only the
                // genuine structural delta instead of ~1200 statements of
                // representation churn (comments, CURRENT_TIMESTAMP defaults,
                // float precision, phantom FK indexes) that never converges. The
                // physical index list distinguishes a real index from one DBAL
                // synthesizes for a foreign key. See Canonicalizer.
                $physicalIndexNames = [];
                foreach ($schemaManager->introspectTableIndexesByUnquotedName($liveName) as $index) {
                    $physicalIndexNames[] = $index->getObjectName()->toString();
                }
                Canonicalizer::reconcile($liveTable, $targetTable, $physicalIndexNames);
                $existingTables[] = $liveTable;
                $tablesToAlter[] = $targetTable;
            } else {
                $tablesToCreate[] = $targetTable;
            }
        }

        // CREATE TABLE statements run first; ALTER TABLE ADD CONSTRAINT
        // (FK) statements run last. Doctrine's MySQL platform emits each
        // table's FKs inline with that table's CREATE — fine for FKs to
        // earlier tables, broken for FKs to later tables (or for grafts where
        // a module adds an FK onto another module's table). Splitting the
        // emission into two passes guarantees every referenced table exists
        // by the time MySQL parses the FK.
        $creates = [];
        $alters  = [];

        foreach ($tablesToCreate as $table) {
            foreach ($platform->getCreateTableSQL($table) as $stmt) {
                if (preg_match('/^\s*ALTER\s+TABLE\b/i', $stmt) === 1) {
                    $alters[] = $stmt;
                } else {
                    $creates[] = $stmt;
                }
            }
        }

        if ($tablesToAlter !== []) {
            $comparator = $schemaManager->createComparator();
            if ($platform instanceof SQLitePlatform) {
                // SQLite rebuilds each changed table straight to its declarative
                // target instead of applying a comparator diff (see sqliteAlters()).
                $alters = array_merge($alters, self::sqliteAlters($platform, $comparator, $existingTables, $tablesToAlter));
            } else {
                $current = new Schema($existingTables);
                $alterTarget = new Schema($tablesToAlter);
                $diff = $comparator->compareSchemas($current, $alterTarget);
                foreach ($platform->getAlterSchemaSQL($diff) as $stmt) {
                    $alters[] = $stmt;
                }
            }
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $alters = self::rewritePostgresUniqueConstraintDrops($connection, $platform, $alters, $tableRenames['sources']);
            $alters = self::quotePostgresRenameIndexNames($platform, $alters);
            $alters = self::fixPostgresColumnTypeChanges($platform, $existingTables, $tablesToAlter, $alters);
        }

        // Compensate for a DBAL/MySQL gap: when a primary key that includes an
        // AUTO_INCREMENT column is rebuilt (a composite PK narrowed to a single
        // column), MySQL forbids dropping the key while the column is still
        // AUTO_INCREMENT, so DBAL first strips AUTO_INCREMENT — but it never
        // restores it, leaving the column a plain INT and the migration forever
        // re-planning the same change. Re-assert the target column's definition
        // after every PK is in place. Runs last (all PKs settled).
        $alters = array_merge($alters, self::autoIncrementRestores($platform, $existingTables, $tablesToAlter));

        // Conversions lead the batch: InnoDB refuses a foreign key referencing a
        // MyISAM table (errno 150), which FOREIGN_KEY_CHECKS=0 does not lift.
        [$firstCharsets, $lastCharsets] = $withTableConversions
            ? self::charsetConversions($connection, $platform, $tablePrefix, $introspectedTables)
            : [[], []];
        $conversions = $withTableConversions
            ? array_merge(self::engineConversions($connection, $platform, $tablePrefix), $firstCharsets)
            : [];

        // Conversions keep the lead: both scans run before any rename, so their
        // statements name the tables as they still stand. An undeclared table goes
        // last, so a conversion that fails there does not stop the declared changes.
        return array_merge($conversions, $tableRenames['sql'], $columnRenames, $creates, $alters, $lastCharsets);
    }

    /**
     * Convert every live utf8mb3 table to utf8mb4, for the same reason as the
     * engine pass: the Comparator never diffs table options.
     *
     * @param array<string, Table> $declaredTables live name => introspected live table
     * @return array{list<string>, list<string>} the statements that start the plan, then the statements that end it
     */
    private static function charsetConversions(
        Connection $connection,
        AbstractPlatform $platform,
        string $tablePrefix,
        array $declaredTables,
    ): array {
        if (!$platform instanceof AbstractMySQLPlatform) {
            return [[], []];
        }

        $tables = self::legacyCharsetTables($connection, $tablePrefix);
        [$dropKeys, $addKeys, $keyTables] = self::legacyCharsetForeignKeys($connection, $platform, $tables);
        // Convert an undeclared table in a dropped key before the ADD statement for that key.
        $keyedUndeclared = array_diff_key(array_intersect_key($tables, $keyTables), $declaredTables);
        [$declared, $undeclared] = self::charsetConversionStatements($platform, array_diff_key($tables, $keyedUndeclared), $declaredTables);

        return [
            array_merge($dropKeys, $declared, self::charsetConversionStatements($platform, $keyedUndeclared, [])[1], $addKeys),
            $undeclared,
        ];
    }

    /**
     * MariaDB refuses to change the charset of a column in a foreign key, even with
     * FOREIGN_KEY_CHECKS=0. Drop each such key before the conversions, and add it again after.
     *
     * @param array<string, string> $tables name => table collation
     * @return array{list<string>, list<string>, array<string, true>} the DROP statements, the ADD statements, the tables in the keys
     */
    private static function legacyCharsetForeignKeys(Connection $connection, AbstractPlatform $platform, array $tables): array
    {
        if (!$platform instanceof MariaDBPlatform || $tables === []) {
            return [[], [], []];
        }

        $rows = $connection->fetchAllAssociative(
            'SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,'
            . ' r.UPDATE_RULE, r.DELETE_RULE, c.CHARACTER_SET_NAME AS CHILD_CHARSET, p.CHARACTER_SET_NAME AS PARENT_CHARSET'
            . ' FROM information_schema.KEY_COLUMN_USAGE k'
            . ' JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA'
            . ' AND r.TABLE_NAME = k.TABLE_NAME AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME'
            . ' JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA = k.TABLE_SCHEMA'
            . ' AND c.TABLE_NAME = k.TABLE_NAME AND c.COLUMN_NAME = k.COLUMN_NAME'
            . ' JOIN information_schema.COLUMNS p ON p.TABLE_SCHEMA = k.REFERENCED_TABLE_SCHEMA'
            . ' AND p.TABLE_NAME = k.REFERENCED_TABLE_NAME AND p.COLUMN_NAME = k.REFERENCED_COLUMN_NAME'
            . ' WHERE k.TABLE_SCHEMA = DATABASE()'
            . ' ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
        );
        $keys = [];
        foreach ($rows as $row) {
            $keys[$row['TABLE_NAME'] . '.' . $row['CONSTRAINT_NAME']][] = $row;
        }
        $quote = $platform->quoteSingleIdentifier(...);
        $drops = $adds = $keyTables = [];
        foreach ($keys as $columns) {
            $key = $columns[0];
            // Take the whole key when one of its columns, on either side, is still utf8mb3.
            $charsets = array_merge(array_column($columns, 'CHILD_CHARSET'), array_column($columns, 'PARENT_CHARSET'));
            if ((!isset($tables[$key['TABLE_NAME']]) && !isset($tables[$key['REFERENCED_TABLE_NAME']]))
                || array_intersect($charsets, ['utf8', 'utf8mb3']) === []
            ) {
                continue;
            }
            $drops[] = sprintf('ALTER TABLE %s DROP FOREIGN KEY %s', $quote($key['TABLE_NAME']), $quote($key['CONSTRAINT_NAME']));
            $adds[] = sprintf(
                'ALTER TABLE %s ADD CONSTRAINT %s FOREIGN KEY (%s) REFERENCES %s (%s) ON DELETE %s ON UPDATE %s',
                $quote($key['TABLE_NAME']),
                $quote($key['CONSTRAINT_NAME']),
                implode(', ', array_map($quote, array_column($columns, 'COLUMN_NAME'))),
                $quote($key['REFERENCED_TABLE_NAME']),
                implode(', ', array_map($quote, array_column($columns, 'REFERENCED_COLUMN_NAME'))),
                $key['DELETE_RULE'],
                $key['UPDATE_RULE'],
            );
            $keyTables[$key['TABLE_NAME']] = $keyTables[$key['REFERENCED_TABLE_NAME']] = true;
        }

        return [$drops, $adds, $keyTables];
    }

    /**
     * Live tables that still hold utf8mb3 text, name => table collation. A table
     * qualifies when its default is utf8mb3, or when its default is utf8mb4 and
     * a column is still utf8mb3. Tables in other charsets are left alone: only
     * utf8mb3 to utf8mb4 is lossless. Scans the whole database like
     * legacyEngineTables(), since a VARCHAR foreign key needs both sides in one
     * charset. Callers must have established that the connection is MySQL or
     * MariaDB.
     *
     * @return array<string, string>
     */
    public static function legacyCharsetTables(Connection $connection, string $tablePrefix = ''): array
    {
        $collations = $connection->fetchAllKeyValue(
            'SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.TABLES'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
            . ' ORDER BY TABLE_NAME',
        );
        $withLegacyColumns = array_flip($connection->fetchFirstColumn(
            'SELECT DISTINCT TABLE_NAME FROM information_schema.COLUMNS'
            . " WHERE TABLE_SCHEMA = DATABASE() AND CHARACTER_SET_NAME IN ('utf8', 'utf8mb3')",
        ));

        $tables = [];
        foreach ($collations as $name => $collation) {
            $collation = (string) $collation;
            if ($tablePrefix !== '' && !str_starts_with($name, $tablePrefix)) {
                continue;
            }
            if (self::isUtf8mb3Collation($collation)
                || (str_starts_with($collation, 'utf8mb4_') && isset($withLegacyColumns[$name]))
            ) {
                $tables[$name] = $collation;
            }
        }

        return $tables;
    }

    /**
     * A declared table gets one MODIFY per utf8mb3 column, rendered from the
     * live column, so each column keeps its type. CONVERT TO would widen TEXT
     * to MEDIUMTEXT, and the declarative diff would then never converge. An
     * undeclared table has no target to diverge from, so it takes CONVERT TO.
     *
     * @param array<string, string> $tables name => table collation
     * @param array<string, Table> $declaredTables live name => introspected live table
     * @return array{list<string>, list<string>} the declared tables, then the undeclared tables
     */
    private static function charsetConversionStatements(AbstractPlatform $platform, array $tables, array $declaredTables): array
    {
        $statements = [];
        $undeclared = [];
        foreach ($tables as $name => $collation) {
            $tableDefault = sprintf(
                'CHARACTER SET utf8mb4 COLLATE %s',
                self::utf8mb4Collation($collation),
            );
            $quotedName = $platform->quoteSingleIdentifier($name);

            if (!isset($declaredTables[$name])) {
                $undeclared[] = sprintf('ALTER TABLE %s CONVERT TO %s', $quotedName, $tableDefault);
                continue;
            }

            $parts = ['DEFAULT ' . $tableDefault];
            foreach ($declaredTables[$name]->getColumns() as $column) {
                $columnCollation = (string) $column->getCollation();
                if (!self::isUtf8mb3Collation($columnCollation)) {
                    continue;
                }
                // Built from public API: DBAL marks its column declaration helpers @internal.
                $part = sprintf(
                    'MODIFY %s %s CHARACTER SET utf8mb4 COLLATE %s %s',
                    $platform->quoteSingleIdentifier($column->getObjectName()->getIdentifier()->getValue()),
                    $column->getType()->getSQLDeclaration($column->toArray(), $platform),
                    self::utf8mb4Collation($columnCollation),
                    $column->getNotnull() ? 'NOT NULL' : 'NULL',
                );
                if ($column->getDefault() !== null) {
                    $part .= ' DEFAULT ' . $platform->quoteStringLiteral((string) $column->getDefault());
                }
                if ($column->getComment() !== '') {
                    $part .= ' COMMENT ' . $platform->quoteStringLiteral($column->getComment());
                }
                $parts[] = $part;
            }
            $statements[] = sprintf('ALTER TABLE %s %s', $quotedName, implode(', ', $parts));
        }

        return [$statements, $undeclared];
    }

    private static function isUtf8mb3Collation(string $collation): bool
    {
        return preg_match('/^utf8(mb3)?_/', $collation) === 1;
    }

    /**
     * The utf8mb4 twin of a utf8mb3 collation: utf8_general_ci and
     * utf8mb3_general_ci both become utf8mb4_general_ci.
     */
    private static function utf8mb4Collation(string $collation): string
    {
        return preg_replace('/^utf8(mb3)?_/', 'utf8mb4_', $collation) ?? $collation;
    }

    /**
     * ALTER TABLE ... ENGINE=InnoDB for every live table still on a legacy engine.
     *
     * DBAL's Comparator never diffs table options, so a live MyISAM table and an
     * InnoDB target produce no ALTER and a migrated store never self-heals.
     *
     * @return list<string>
     */
    private static function engineConversions(Connection $connection, AbstractPlatform $platform, string $tablePrefix): array
    {
        if (!$platform instanceof AbstractMySQLPlatform) {
            return [];
        }

        return self::engineConversionStatements($platform, array_keys(self::legacyEngineTables($connection, $tablePrefix)));
    }

    /**
     * Live tables still on a non-transactional engine, name => engine. Scans the
     * whole database, not just declared tables: plan() introspects only what the
     * target declares, and legacy tables are typically third-party. Callers must
     * have established that the connection is MySQL or MariaDB.
     *
     * @return array<string, string>
     */
    public static function legacyEngineTables(Connection $connection, string $tablePrefix = ''): array
    {
        $sql = 'SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES'
            . " WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'"
            . ' AND ENGINE IN (' . implode(', ', array_fill(0, count(self::LEGACY_ENGINES), '?')) . ')'
            . ' ORDER BY TABLE_NAME';

        $tables = $connection->fetchAllKeyValue($sql, self::LEGACY_ENGINES);

        // A prefix means the database may be shared. Filtered here rather than
        // with LIKE, whose pattern would need escaping ('_' is a wildcard).
        if ($tablePrefix !== '') {
            $tables = array_filter(
                $tables,
                static fn(string $name): bool => str_starts_with($name, $tablePrefix),
                ARRAY_FILTER_USE_KEY,
            );
        }

        return $tables;
    }

    /**
     * Split from the query above so the statement shape is testable connectionless.
     *
     * @param list<string> $tableNames
     * @return list<string>
     */
    private static function engineConversionStatements(AbstractPlatform $platform, array $tableNames): array
    {
        return array_map(
            static fn(string $name): string => sprintf(
                'ALTER TABLE %s ENGINE=InnoDB',
                $platform->quoteSingleIdentifier($name),
            ),
            $tableNames,
        );
    }

    /**
     * Reconcile SQLite tables, one at a time.
     *
     * SQLite's ALTER TABLE only does ADD COLUMN / RENAME: it can't change a
     * column type, drop a column, or add/drop a foreign key, so DBAL applies
     * most diffs by rebuilding the table from the live table plus the diff.
     * Here, any table whose canonicalized live form differs from its target is
     * rebuilt straight to the target instead (sqliteRebuildTable). The result
     * is identical to a fresh install, and a re-run is a no-op. A table that
     * already matches emits nothing, keeping a born-declarative database a no-op.
     *
     * Rebuilding even for a purely additive change means a full-table copy, but
     * on SQLite (small-shop installs, schema changes only at module-upgrade
     * time) that buys one provably-convergent path instead of a fragile
     * additive-vs-rebuild classifier sitting on DBAL's most bug-prone surface.
     *
     * @param list<Table> $liveTables   canonicalized live tables
     * @param list<Table> $targetTables declarative targets, parallel to $liveTables
     * @return list<string>
     */
    private static function sqliteAlters(SQLitePlatform $platform, Comparator $comparator, array $liveTables, array $targetTables): array
    {
        $sql = [];
        foreach ($targetTables as $i => $target) {
            if ($comparator->compareTables($liveTables[$i], $target)->isEmpty()) {
                continue;
            }
            $sql = array_merge($sql, self::sqliteRebuildTable($platform, $liveTables[$i], $target));
        }

        return $sql;
    }

    /**
     * Rebuild a SQLite table to its declarative target, preserving the data of
     * every column the live table and the target share.
     *
     * SQLite can only change a table by recreating it, so this snapshots the
     * shared columns into a temporary table, drops the original (which frees its
     * globally-named indexes), recreates the table from the full target —
     * getCreateTableSQL emits the primary key and foreign keys inline plus a
     * CREATE INDEX per declared index — copies the data back, and drops the
     * snapshot. Building from the full target rather than a diff is what keeps
     * the migrated table identical to a fresh install and makes a re-run a
     * no-op. The batch runs with foreign keys disabled (execute() → startSetup),
     * so dropping and recreating a referenced table mid-batch is safe.
     *
     * @return list<string>
     */
    private static function sqliteRebuildTable(SQLitePlatform $platform, Table $live, Table $target): array
    {
        $tableName = self::unquote($target->getObjectName()->toString());
        $quoted = $platform->quoteSingleIdentifier($tableName);
        $temp = $platform->quoteSingleIdentifier('__maho_tmp_' . $tableName);

        $targetColumns = [];
        foreach ($target->getColumns() as $column) {
            $targetColumns[strtolower(self::unquote($column->getObjectName()->toString()))] = true;
        }

        $liveColumns = [];
        $shared = [];
        foreach ($live->getColumns() as $column) {
            $name = self::unquote($column->getObjectName()->toString());
            $liveColumns[strtolower($name)] = true;
            if (isset($targetColumns[strtolower($name)])) {
                $shared[] = $platform->quoteSingleIdentifier($name);
            }
        }

        // Unreachable via plan() (the Canonicalizer merges undeclared live
        // columns into the target), but a rebuild that can carry nothing over
        // must refuse rather than silently discard the table's rows.
        if ($shared === []) {
            throw new UnsupportedMigrationException(sprintf(
                'Cannot rebuild "%s": the live table shares no columns with the declarative target, '
                . 'so its rows cannot be preserved.',
                $tableName,
            ));
        }

        // A new NOT NULL column with no default can't be backfilled for existing
        // rows; refuse with guidance rather than emit an INSERT that fails.
        foreach ($target->getColumns() as $column) {
            $name = self::unquote($column->getObjectName()->toString());
            if (!isset($liveColumns[strtolower($name)]) && $column->getNotnull()
                && $column->getDefault() === null && !$column->getAutoincrement()
            ) {
                throw new UnsupportedMigrationException(sprintf(
                    'Cannot add NOT NULL column "%s" to "%s" without a default: existing rows have no value for it. '
                    . 'Give the column a default or make it nullable.',
                    $name,
                    $tableName,
                ));
            }
        }

        $columnList = implode(', ', $shared);

        $sql = [];
        $sql[] = sprintf('CREATE TEMPORARY TABLE %s AS SELECT %s FROM %s', $temp, $columnList, $quoted);
        $sql[] = 'DROP TABLE ' . $quoted;
        foreach ($platform->getCreateTableSQL($target) as $stmt) {
            $sql[] = $stmt;
        }
        $sql[] = sprintf('INSERT INTO %s (%s) SELECT %s FROM %s', $quoted, $columnList, $columnList, $temp);
        $sql[] = 'DROP TABLE ' . $temp;

        return $sql;
    }

    /**
     * Strip surrounding identifier quotes (introspection quotes names; the
     * declarative schema leaves them bare).
     */
    private static function unquote(string $identifier): string
    {
        return trim($identifier, '"`');
    }

    /**
     * Build the AUTO_INCREMENT-restoring ALTERs described in plan(). For each
     * altered table whose primary-key columns changed and whose target declares
     * an AUTO_INCREMENT column, emit a MODIFY that re-asserts that column.
     *
     * MySQL-only: on PostgreSQL and SQLite autoincrement is an identity/rowid
     * property that survives a primary-key rebuild, so the gap does not arise
     * and the MODIFY syntax would not apply.
     *
     * @param list<\Doctrine\DBAL\Schema\Table> $liveTables
     * @param list<\Doctrine\DBAL\Schema\Table> $targetTables parallel to $liveTables
     * @return list<string>
     */
    private static function autoIncrementRestores(AbstractPlatform $platform, array $liveTables, array $targetTables): array
    {
        if (!$platform instanceof AbstractMySQLPlatform) {
            return [];
        }

        $restores = [];
        foreach ($targetTables as $i => $target) {
            $autoColumn = null;
            foreach ($target->getColumns() as $column) {
                if ($column->getAutoincrement()) {
                    $autoColumn = $column;
                    break;
                }
            }
            if ($autoColumn === null || self::primaryKeyColumns($liveTables[$i]) === self::primaryKeyColumns($target)) {
                continue;
            }
            // The column is, by the time this runs, already part of the rebuilt
            // primary key, so a plain MODIFY re-asserting AUTO_INCREMENT is both
            // valid and sufficient. The type SQL comes from the column's own
            // type (public Type::getSQLDeclaration, e.g. "INT UNSIGNED").
            $restores[] = sprintf(
                'ALTER TABLE %s MODIFY %s %s AUTO_INCREMENT NOT NULL',
                $target->getObjectName()->toString(),
                $autoColumn->getObjectName()->toString(),
                $autoColumn->getType()->getSQLDeclaration($autoColumn->toArray(), $platform),
            );
        }

        return $restores;
    }

    /**
     * Rewrite `DROP INDEX` into `ALTER TABLE ... DROP CONSTRAINT` for any index
     * Postgres backs with a UNIQUE constraint.
     *
     * Postgres implements every UNIQUE/PRIMARY constraint with an index of the
     * same name, and refuses `DROP INDEX` on it ("cannot drop index ... because
     * constraint ... requires it") — the owning constraint must be dropped
     * instead. DBAL introspects these as ordinary unique indexes, so when the
     * target no longer keeps one (e.g. an install left a unique key behind under
     * an older column set that a later upgrade replaced) the comparator emits a
     * plain `DROP INDEX` that Postgres will not execute. DBAL already special-
     * cases the primary key this way but not other unique constraints, because
     * the index name alone does not say whether a constraint owns it; the
     * catalog does. Look up the owning constraints once and rewrite the drops.
     *
     * @param list<string> $statements
     * @param array<string, string> $renameSources target name => live source
     *        name; the catalog still names a to-be-renamed table by its old
     *        name, but the rewritten drop runs after the rename
     * @return list<string>
     */
    private static function rewritePostgresUniqueConstraintDrops(Connection $connection, AbstractPlatform $platform, array $statements, array $renameSources = []): array
    {
        $renamedTo = array_flip($renameSources);
        $constraintTable = [];
        $rows = $connection->fetchAllAssociative(
            "SELECT c.conname, t.relname
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE c.contype = 'u'
                AND n.nspname = ANY (current_schemas(false))",
        );
        foreach ($rows as $row) {
            $constraintTable[$row['conname']] = $row['relname'];
        }
        if ($constraintTable === []) {
            return $statements;
        }

        return array_map(static function (string $stmt) use ($constraintTable, $platform, $renamedTo): string {
            if (preg_match('/^\s*DROP\s+INDEX\s+(?:CONCURRENTLY\s+)?(?:IF\s+EXISTS\s+)?"?([^"\s;]+)"?\s*;?\s*$/i', $stmt, $m) !== 1) {
                return $stmt;
            }
            $name = $m[1];
            if (!isset($constraintTable[$name])) {
                return $stmt;
            }
            $tableName = $renamedTo[$constraintTable[$name]] ?? $constraintTable[$name];

            return sprintf(
                'ALTER TABLE %s DROP CONSTRAINT %s',
                $platform->quoteSingleIdentifier($tableName),
                $platform->quoteSingleIdentifier($name),
            );
        }, $statements);
    }

    /**
     * Quote the old index name in `ALTER INDEX <old> RENAME TO <new>` on
     * Postgres.
     *
     * DBAL's PostgreSQLPlatform::getRenameIndexSQL interpolates the old name
     * verbatim while quoting only the new one, so when the rename detection
     * matches an index a legacy install named as a bare hash (the old adapter
     * hashed index names to hex, which can begin with a digit) the statement
     * `ALTER INDEX 0abc... RENAME TO ...` is a syntax error: an unquoted
     * identifier may not start with a digit. Re-quote the old name so the
     * rename Postgres would otherwise reject executes; the new name is already
     * quoted by DBAL.
     *
     * @param list<string> $statements
     * @return list<string>
     */
    private static function quotePostgresRenameIndexNames(AbstractPlatform $platform, array $statements): array
    {
        return array_map(static function (string $stmt) use ($platform): string {
            // DBAL interpolates the old name verbatim (unquoted), so capture
            // everything between the keywords rather than assuming its shape.
            if (preg_match('/^(\s*ALTER\s+INDEX\s+)(.+?)(\s+RENAME\s+TO\s+.+)$/i', $stmt, $m) !== 1) {
                return $stmt;
            }

            return $m[1] . $platform->quoteSingleIdentifier(trim($m[2], '"')) . $m[3];
        }, $statements);
    }

    /**
     * Make a Postgres `ALTER ... TYPE` succeed when the old column has no
     * implicit cast to the new type.
     *
     * Postgres changes a column's type unaided only when an implicit cast exists
     * (e.g. real to double precision); without one it refuses both the data
     * ("column ... cannot be cast automatically") and the existing default
     * ("default for column ... cannot be cast"). DBAL emits a bare `ALTER ...
     * TYPE` (plus its own later `SET DEFAULT`) with no hook for either, so this
     * supplies the missing `USING` clause for the two legacy-to-declarative
     * conversions that need it, and drops the stale default first (DBAL re-sets
     * the target default afterwards):
     *
     *   - boolean to a numeric type: some legacy boolean flags become smallint
     *     (the app filters them with `= 1`, which a Postgres boolean rejects).
     *     Convert through an explicit integer cast, preserving 0/1.
     *   - a legacy bigint IP column (ip2long) to binary: the integer encoding has
     *     no faithful reinterpretation as the new binary format, so the values
     *     are discarded (`USING NULL`). The columns are nullable IP-tracking
     *     fields; a fresh install has no such data either.
     *
     * The default is dropped before the type change and re-set from the target
     * afterwards rather than left to DBAL: DBAL treats a boolean `false` and a
     * smallint `0` as the same default and so never emits the `SET DEFAULT`,
     * which would otherwise leave the migrated column without its target default.
     *
     * Other type changes pass through: DBAL relies on their implicit cast.
     *
     * @param list<\Doctrine\DBAL\Schema\Table> $liveTables canonicalized live tables
     * @param list<\Doctrine\DBAL\Schema\Table> $targetTables declarative target tables
     * @param list<string> $statements
     * @return list<string>
     */
    private static function fixPostgresColumnTypeChanges(PostgreSQLPlatform $platform, array $liveTables, array $targetTables, array $statements): array
    {
        $liveColumnTypes = [];
        $liveDefaults = [];
        foreach ($liveTables as $table) {
            $tableName = strtolower(trim($table->getObjectName()->toString(), '"'));
            foreach ($table->getColumns() as $column) {
                $columnName = strtolower(trim($column->getObjectName()->toString(), '"'));
                $liveColumnTypes[$tableName][$columnName] = $column->getType();
                $liveDefaults[$tableName][$columnName] = $column->getDefault();
            }
        }
        $targetDefaults = [];
        foreach ($targetTables as $table) {
            $tableName = strtolower(trim($table->getObjectName()->toString(), '"'));
            foreach ($table->getColumns() as $column) {
                $columnName = strtolower(trim($column->getObjectName()->toString(), '"'));
                $targetDefaults[$tableName][$columnName] = $column->getDefault();
            }
        }

        $result = [];
        foreach ($statements as $stmt) {
            if (preg_match('/^ALTER\s+TABLE\s+(\S+)\s+ALTER\s+(\S+)\s+TYPE\s+(.+?)\s*$/i', $stmt, $m) !== 1) {
                $result[] = $stmt;
                continue;
            }
            $table = $m[1];
            $column = $m[2];
            $newType = strtoupper(trim($m[3]));
            $tableKey = strtolower(trim($table, '"'));
            $columnKey = strtolower(trim($column, '"'));
            $liveType = $liveColumnTypes[$tableKey][$columnKey] ?? null;

            if ($newType === 'BYTEA') {
                $using = 'NULL';
            } elseif ($liveType instanceof BooleanType) {
                $using = "{$column}::integer";
            } else {
                $result[] = $stmt;
                continue;
            }

            if (($liveDefaults[$tableKey][$columnKey] ?? null) !== null) {
                $result[] = "ALTER TABLE {$table} ALTER {$column} DROP DEFAULT";
            }
            $result[] = $stmt . " USING {$using}";

            $default = $targetDefaults[$tableKey][$columnKey] ?? null;
            if ($default !== null) {
                if ($default instanceof DefaultExpression) {
                    $literal = $default->toSQL($platform);
                } elseif (is_numeric($default)) {
                    $literal = (string) $default;
                } else {
                    $literal = "'" . str_replace("'", "''", (string) $default) . "'";
                }
                $result[] = "ALTER TABLE {$table} ALTER {$column} SET DEFAULT {$literal}";
            }
        }

        return $result;
    }

    /**
     * Lower-cased, unquoted primary-key column names of a table, or [] when it
     * has none. Introspection returns the names quoted; the declarative schema
     * leaves them bare, so both are normalized for comparison.
     *
     * @return list<string>
     */
    private static function primaryKeyColumns(Table $table): array
    {
        $primaryKey = $table->getPrimaryKeyConstraint();
        if ($primaryKey === null) {
            return [];
        }

        return array_map(
            static fn($name): string => strtolower(trim($name->toString(), '"`')),
            $primaryKey->getColumnNames(),
        );
    }

    /**
     * Execute the given statements, suspending foreign-key enforcement for the
     * whole batch via the adapter's startSetup()/endSetup().
     *
     * This is the single mechanism that makes the DBAL-generated diff applicable
     * on every engine. The comparator emits FK-bearing rebuilds (SQLite recreates
     * a table by copying to a temp table, dropping, recreating and re-inserting;
     * MySQL drops/re-adds constraints) whose intermediate states violate the
     * still-live foreign keys. startSetup() turns enforcement off the way each
     * engine expects — MySQL FOREIGN_KEY_CHECKS=0, Postgres session_replication_role,
     * SQLite PRAGMA foreign_keys=OFF — and endSetup() restores it. Same call,
     * engine-specific implementation, so the applier stays platform-agnostic.
     *
     * On Postgres and SQLite the batch also runs inside a transaction: DDL is
     * transactional on both, so a mid-batch failure rolls back cleanly instead
     * of leaving the schema half-migrated. On SQLite this also makes the table
     * rebuilds atomic — without it a failure between the DROP and the copy-back
     * INSERT would lose the table's rows, since the snapshot is a TEMPORARY
     * table that dies with the connection. The foreign_keys pragma is issued by
     * startSetup() before the transaction begins (PRAGMA foreign_keys is a
     * no-op inside one). MySQL/MariaDB DDL auto-commits per statement (no
     * rollback possible), so it runs the loop bare.
     *
     * @param list<string> $sql
     */
    public static function execute(AdapterInterface $adapter, array $sql): void
    {
        $connection = $adapter->getConnection();
        $platform = $connection->getDatabasePlatform();

        $adapter->startSetup();
        try {
            if ($platform instanceof PostgreSQLPlatform || $platform instanceof SQLitePlatform) {
                $connection->transactional(static function (Connection $c) use ($sql): void {
                    foreach ($sql as $stmt) {
                        $c->executeStatement($stmt);
                    }
                });
            } else {
                foreach ($sql as $stmt) {
                    $connection->executeStatement($stmt);
                }
            }
        } finally {
            $adapter->endSetup();
        }
    }

    /**
     * Convenience: collect schema from all modules, plan and execute the diff.
     * Intended for non-interactive contexts (the Migrate command, the installer
     * bootstrap).
     *
     * The plan is applied as-is on every engine, including SQLite. MySQL/MariaDB
     * and Postgres reconcile with native in-place ALTERs (rename, type change,
     * column/index/FK drop). SQLite can't ALTER columns or foreign keys, so
     * plan() reconciles each changed SQLite table by recreating it to the
     * declarative target (see sqliteRebuildTable); the only refusal is a new
     * NOT NULL column with no default on a table that already holds rows, which
     * no engine can backfill — that surfaces as UnsupportedMigrationException.
     *
     * @return array{contributors: list<string>, executed: list<string>}
     */
    public static function applyAll(AdapterInterface $adapter): array
    {
        [$target, $contributors] = Collector::collect();
        if ($contributors === []) {
            return ['contributors' => [], 'executed' => []];
        }

        $connection = $adapter->getConnection();
        $sql = self::plan($connection, $target, Collector::tablePrefix());
        if ($sql === []) {
            return ['contributors' => $contributors, 'executed' => []];
        }

        self::execute($adapter, $sql);
        return ['contributors' => $contributors, 'executed' => $sql];
    }
}

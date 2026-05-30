<?php

/**
 * Maho
 *
 * @package    Maho_Db
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\BooleanType;
use Maho\Db\Adapter\AdapterInterface;

final class Applier
{
    /**
     * Compute the SQL needed to reconcile the live database with the target Schema.
     *
     * The introspected current schema is scoped to only the tables declared in
     * $target, so unrelated existing tables are never considered for DROP.
     *
     * @return list<string> SQL statements (empty when no changes are needed)
     */
    public static function plan(Connection $connection, Schema $target): array
    {
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

        $existingTables = [];
        $tablesToCreate = [];
        $tablesToAlter = [];
        foreach ($target->getTables() as $targetTable) {
            $name = $targetTable->getObjectName()->getUnqualifiedName()->getValue();
            if (isset($existing[$name])) {
                $liveTable = $schemaManager->introspectTableByUnquotedName($name);
                // Reduce the live table and its declarative target to
                // parity-equivalent form, so the Comparator below emits only the
                // genuine structural delta instead of ~1200 statements of
                // representation churn (comments, CURRENT_TIMESTAMP defaults,
                // float precision, phantom FK indexes) that never converges. The
                // physical index list distinguishes a real index from one DBAL
                // synthesizes for a foreign key. See Canonicalizer.
                $physicalIndexNames = [];
                foreach ($schemaManager->introspectTableIndexesByUnquotedName($name) as $index) {
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
            $current = new Schema($existingTables);
            $alterTarget = new Schema($tablesToAlter);
            $comparator = $schemaManager->createComparator();
            $diff = $comparator->compareSchemas($current, $alterTarget);
            foreach ($platform->getAlterSchemaSQL($diff) as $stmt) {
                $alters[] = $stmt;
            }
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $alters = self::rewritePostgresUniqueConstraintDrops($connection, $platform, $alters);
            $alters = self::quotePostgresRenameIndexNames($platform, $alters);
            $alters = self::fixPostgresColumnTypeChanges($existingTables, $tablesToAlter, $alters);
        }

        // Compensate for a DBAL/MySQL gap: when a primary key that includes an
        // AUTO_INCREMENT column is rebuilt (a composite PK narrowed to a single
        // column), MySQL forbids dropping the key while the column is still
        // AUTO_INCREMENT, so DBAL first strips AUTO_INCREMENT — but it never
        // restores it, leaving the column a plain INT and the migration forever
        // re-planning the same change. Re-assert the target column's definition
        // after every PK is in place. Runs last (all PKs settled).
        $alters = array_merge($alters, self::autoIncrementRestores($platform, $existingTables, $tablesToAlter));

        return array_merge($creates, $alters);
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
     * @return list<string>
     */
    private static function rewritePostgresUniqueConstraintDrops(Connection $connection, AbstractPlatform $platform, array $statements): array
    {
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

        return array_map(static function (string $stmt) use ($constraintTable, $platform): string {
            if (preg_match('/^\s*DROP\s+INDEX\s+(?:CONCURRENTLY\s+)?(?:IF\s+EXISTS\s+)?"?([^"\s;]+)"?\s*;?\s*$/i', $stmt, $m) !== 1) {
                return $stmt;
            }
            $name = $m[1];
            if (!isset($constraintTable[$name])) {
                return $stmt;
            }

            return sprintf(
                'ALTER TABLE %s DROP CONSTRAINT %s',
                $platform->quoteSingleIdentifier($constraintTable[$name]),
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
            if (preg_match('/^(\s*ALTER\s+INDEX\s+)(\S+)(\s+RENAME\s+TO\s+.+)$/i', $stmt, $m) !== 1) {
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
    private static function fixPostgresColumnTypeChanges(array $liveTables, array $targetTables, array $statements): array
    {
        $liveColumnTypes = [];
        foreach ($liveTables as $table) {
            $tableName = strtolower(trim($table->getObjectName()->toString(), '"'));
            foreach ($table->getColumns() as $column) {
                $columnName = strtolower(trim($column->getObjectName()->toString(), '"'));
                $liveColumnTypes[$tableName][$columnName] = $column->getType();
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

            $result[] = "ALTER TABLE {$table} ALTER {$column} DROP DEFAULT";
            $result[] = $stmt . " USING {$using}";

            $default = $targetDefaults[$tableKey][$columnKey] ?? null;
            if ($default !== null) {
                $literal = is_numeric($default) ? (string) $default : "'" . str_replace("'", "''", (string) $default) . "'";
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
     * Statements considered destructive — data loss or app-visible breakage.
     * Covers: DROP at top level, ALTER TABLE ... DROP <anything>, RENAME at
     * top level (Postgres' ALTER TABLE ... RENAME TO is emitted as a separate
     * RENAME stmt by some platforms), ALTER TABLE ... RENAME COLUMN (renames
     * out from under app code), ALTER TABLE ... MODIFY/CHANGE/ALTER COLUMN
     * (can coerce data or trip NOT NULL), and TRUNCATE.
     *
     * Doctrine's MySQL platform collapses multiple alterations into a single
     * comma-separated `ALTER TABLE foo ADD ..., DROP ..., CHANGE ...` statement
     * (AbstractMySQLPlatform::getAlterTableSQL), so a destructive clause can
     * trail a benign leading ADD. We therefore scan the whole ALTER TABLE body
     * for a destructive verb at any clause boundary (right after the table name
     * or after a clause-separating comma), not just the first clause. The \b
     * after each verb keeps comma-separated identifier lists inside parentheses
     * (e.g. "ADD PRIMARY KEY (a, change_log)") from matching, since the word
     * boundary fails against an identifier like "change_log".
     *
     * @param list<string> $sql
     * @return list<string>
     */
    public static function destructiveStatements(array $sql): array
    {
        return array_values(array_filter($sql, static function (string $s): bool {
            if (preg_match('/^\s*(DROP|TRUNCATE|RENAME)\b/i', $s) === 1) {
                return true;
            }

            return preg_match('/^\s*ALTER\s+TABLE\b/i', $s) === 1
                && preg_match(
                    '/(?:ALTER\s+TABLE\s+\S+\s+|,\s*)(DROP|RENAME|MODIFY|CHANGE|ALTER)\b/i',
                    $s,
                ) === 1;
        }));
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
     * On Postgres the batch also runs inside a transaction: pg DDL is
     * transactional, so a mid-batch failure rolls back cleanly instead of
     * leaving the schema half-migrated. MySQL/MariaDB DDL auto-commits per
     * statement (no rollback possible), and SQLite needs the foreign_keys
     * pragma to take effect outside any transaction — both run the loop bare.
     *
     * @param list<string> $sql
     */
    public static function execute(AdapterInterface $adapter, array $sql): void
    {
        $connection = $adapter->getConnection();
        $platform = $connection->getDatabasePlatform();

        $adapter->startSetup();
        try {
            if ($platform instanceof PostgreSQLPlatform) {
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
     * The plan is applied as-is on every engine: a migration that needs an
     * in-place ALTER (rename, type change, column/index/FK drop) just runs it,
     * because that is the migration's job. MySQL/MariaDB and Postgres perform
     * these as native ALTERs without rebuilding tables.
     *
     * The one exception is SQLite. It has no real column types, so reconciling
     * an existing database to the declarative schema can mean changing column
     * types, which SQLite can only do by rebuilding the whole table — and DBAL's
     * SQLite rebuild silently drops foreign keys and indexes, so the result
     * never converges. Rather than corrupt the schema, we detect that a SQLite
     * apply would require destructive rebuilds and refuse with
     * UnsupportedMigrationException, directing the operator to reinstall.
     * Fresh SQLite installs (all CREATE TABLE) and purely additive SQLite
     * migrations (ADD COLUMN / CREATE INDEX / CREATE TABLE) carry no
     * destructive statements and apply normally.
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
        $sql = self::plan($connection, $target);
        if ($sql === []) {
            return ['contributors' => $contributors, 'executed' => []];
        }

        if ($connection->getDatabasePlatform() instanceof SQLitePlatform
            && self::destructiveStatements($sql) !== []
        ) {
            throw new UnsupportedMigrationException(
                "This SQLite database can't be upgraded to the current schema in place: the upgrade "
                . 'changes column types, which SQLite can only apply by rebuilding tables, and DBAL\'s '
                . 'SQLite rebuild drops foreign keys and indexes. In-place schema upgrades are not '
                . 'supported on SQLite. Use MySQL/MariaDB or PostgreSQL if you need in-place upgrades, '
                . 'or start from a fresh install (./maho install ...).',
            );
        }

        self::execute($adapter, $sql);
        return ['contributors' => $contributors, 'executed' => $sql];
    }
}

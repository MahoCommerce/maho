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
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
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
                $existingTables[] = $schemaManager->introspectTableByUnquotedName($name);
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

        return array_merge($creates, $alters);
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
     * a legacy install to the declarative schema means changing column types,
     * which SQLite can only do by rebuilding the whole table — and DBAL's
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

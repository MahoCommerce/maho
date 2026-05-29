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
use Doctrine\DBAL\Schema\Schema;
use RuntimeException;

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
        // introspectTableNames() returns OptionallyQualifiedName objects.
        $existing = [];
        foreach ($schemaManager->introspectTableNames() as $existingName) {
            $existing[$existingName->toString()] = true;
        }

        $existingTables = [];
        $tablesToCreate = [];
        $tablesToAlter = [];
        foreach ($target->getTables() as $targetTable) {
            $name = $targetTable->getObjectName()->toString();
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
     * Execute the given statements against the connection.
     *
     * On Postgres the whole batch runs inside a transaction: pg DDL is
     * transactional, so a mid-batch failure rolls back cleanly instead of
     * leaving the schema half-migrated. MySQL/MariaDB DDL auto-commits per
     * statement (no rollback possible), and SQLite supports transactional
     * DDL but the legacy adapter's connection is single-writer enough that
     * the extra ceremony buys nothing — both platforms run the loop bare.
     *
     * @param list<string> $sql
     */
    public static function execute(Connection $connection, array $sql): void
    {
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof PostgreSQLPlatform) {
            $connection->transactional(static function (Connection $c) use ($sql): void {
                foreach ($sql as $stmt) {
                    $c->executeStatement($stmt);
                }
            });
            return;
        }

        foreach ($sql as $stmt) {
            $connection->executeStatement($stmt);
        }
    }

    /**
     * Convenience: collect schema from all modules, plan and execute the diff.
     * Intended for non-interactive contexts (the Migrate command, the installer
     * bootstrap). The destructive-statement guard is enforced unless
     * $allowDestructive is true, so a drifted live schema aborts loudly rather
     * than silently dropping or rewriting columns.
     *
     * @return array{contributors: list<string>, executed: list<string>}
     */
    public static function applyAll(Connection $connection, bool $allowDestructive = false): array
    {
        [$target, $contributors] = Collector::collect();
        if ($contributors === []) {
            return ['contributors' => [], 'executed' => []];
        }

        $sql = self::plan($connection, $target);
        if ($sql === []) {
            return ['contributors' => $contributors, 'executed' => []];
        }

        if (!$allowDestructive) {
            $destructive = self::destructiveStatements($sql);
            if ($destructive !== []) {
                throw new RuntimeException(
                    "Refusing to apply declarative schema with destructive statements:\n  "
                    . implode("\n  ", $destructive),
                );
            }
        }

        self::execute($connection, $sql);
        return ['contributors' => $contributors, 'executed' => $sql];
    }
}

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
     * @param list<string> $sql
     * @return list<string>
     */
    public static function destructiveStatements(array $sql): array
    {
        $pattern = '/^\s*('
            . 'DROP\b'
            . '|TRUNCATE\b'
            . '|RENAME\b'
            . '|ALTER\s+TABLE\s+\S+\s+(DROP|RENAME|MODIFY|CHANGE|ALTER)\b'
            . ')/i';
        return array_values(array_filter(
            $sql,
            static fn(string $s) => preg_match($pattern, $s) === 1,
        ));
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
     * Convenience: collect schema from all modules, plan and execute the diff
     * without destructive guard. Intended for non-interactive contexts (the
     * Migrate command, the installer bootstrap).
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

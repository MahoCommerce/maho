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

        $existingTables = [];
        $tablesToCreate = [];
        $tablesToAlter = [];
        foreach ($target->getTables() as $targetTable) {
            $name = $targetTable->getObjectName()->toString();
            if ($schemaManager->tablesExist([$name])) {
                $existingTables[] = $schemaManager->introspectTableByUnquotedName($name);
                $tablesToAlter[] = $targetTable;
            } else {
                $tablesToCreate[] = $targetTable;
            }
        }

        $sql = [];

        foreach ($tablesToCreate as $table) {
            foreach ($platform->getCreateTableSQL($table) as $stmt) {
                $sql[] = $stmt;
            }
        }

        if ($tablesToAlter !== []) {
            $current = new Schema($existingTables);
            $alterTarget = new Schema($tablesToAlter);
            $comparator = $schemaManager->createComparator();
            $diff = $comparator->compareSchemas($current, $alterTarget);
            foreach ($platform->getAlterSchemaSQL($diff) as $stmt) {
                $sql[] = $stmt;
            }
        }

        return $sql;
    }

    /**
     * Statements considered destructive (drop column / table / index / FK).
     *
     * @param list<string> $sql
     * @return list<string>
     */
    public static function destructiveStatements(array $sql): array
    {
        return array_values(array_filter(
            $sql,
            static fn(string $s) => preg_match('/^\s*(DROP|ALTER\s+TABLE\s+\S+\s+DROP)/i', $s) === 1,
        ));
    }

    /**
     * Execute the given statements against the connection.
     *
     * @param list<string> $sql
     */
    public static function execute(Connection $connection, array $sql): void
    {
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

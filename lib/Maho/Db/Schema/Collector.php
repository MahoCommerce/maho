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

use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Mage;
use Maho;
use RuntimeException;

final class Collector
{
    /**
     * Walk every active module, load its sql/schema.php closure if present,
     * and let each closure contribute tables to a shared Schema instance.
     *
     * Module load order respects depends_on, so a later module can
     * $schema->getTable('foo') on a table defined by an earlier one.
     *
     * @return list<string> names of modules that contributed a schema
     */
    public static function collect(Schema $schema): array
    {
        $contributors = [];
        $modules = Mage::getConfig()->getNode('modules')->children();
        foreach ($modules as $modName => $module) {
            if (!$module->is('active')) {
                continue;
            }

            $sqlDir = Mage::getConfig()->getModuleDir('sql', (string) $modName);
            $file = Maho::findFile("$sqlDir/schema.php");
            if ($file === false) {
                continue;
            }

            $closure = require $file;
            if (!is_callable($closure)) {
                throw new RuntimeException(
                    "Expected $file to return a callable that mutates the Schema, got " . get_debug_type($closure),
                );
            }

            $closure($schema);
            $contributors[] = (string) $modName;
        }

        self::applyTablePrefix($schema);
        self::applyTableDefaults($schema);

        return $contributors;
    }

    /**
     * Convenience: build a target Schema from all module contributions.
     */
    public static function buildTargetSchema(): Schema
    {
        $schema = new Schema();
        self::collect($schema);
        return $schema;
    }

    /**
     * Apply the same table-level charset/collation Maho's legacy adapter emits
     * (Maho\Db\Ddl\Table::$_options defaults to charset=utf8, collate=utf8_general_ci).
     * Without this, MySQL refuses foreign keys between a declarative table
     * (database-default charset, often utf8mb4) and a legacy table (utf8).
     *
     * Authors may still override per table via $table->addOption('charset', ...).
     */
    private static function applyTableDefaults(Schema $schema): void
    {
        foreach ($schema->getTables() as $table) {
            if (!$table->hasOption('charset')) {
                $table->addOption('charset', 'utf8');
            }
            if (!$table->hasOption('collation')) {
                $table->addOption('collation', 'utf8_general_ci');
            }
        }
    }

    /**
     * Schema authors declare table names without the configured table_prefix
     * (matching the convention M2's db_schema.xml uses). After all closures
     * have populated the schema, rewrite every table name and every foreign
     * key reference to include the prefix.
     */
    private static function applyTablePrefix(Schema $schema): void
    {
        $prefix = (string) Mage::getConfig()->getTablePrefix();
        if ($prefix === '') {
            return;
        }

        $oldNames = [];
        foreach ($schema->getTables() as $table) {
            $oldNames[] = $table->getName();
        }

        // Capture each table's foreign-key config before renaming, since
        // dropForeignKey + addForeignKeyConstraint is the only way to mutate
        // the referenced-table name on an existing constraint.
        $fkPlan = [];
        foreach ($schema->getTables() as $table) {
            $tableName = $table->getName();
            foreach ($table->getForeignKeys() as $fk) {
                $fkPlan[] = [
                    'table' => $tableName,
                    'fkName' => $fk->getObjectName()?->toString(),
                    'foreignTable' => $fk->getReferencedTableName()->toString(),
                    'localColumns' => array_map(
                        static fn (UnqualifiedName $n) => $n->toString(),
                        $fk->getReferencingColumnNames(),
                    ),
                    'foreignColumns' => array_map(
                        static fn (UnqualifiedName $n) => $n->toString(),
                        $fk->getReferencedColumnNames(),
                    ),
                    'options' => [
                        'onUpdate' => $fk->getOnUpdateAction()->value,
                        'onDelete' => $fk->getOnDeleteAction()->value,
                    ],
                ];
            }
        }

        foreach ($oldNames as $oldName) {
            $schema->renameTable($oldName, $prefix . $oldName);
        }

        foreach ($fkPlan as $entry) {
            $table = $schema->getTable($prefix . $entry['table']);
            if ($entry['fkName'] !== null) {
                $table->dropForeignKey($entry['fkName']);
            }
            $table->addForeignKeyConstraint(
                $prefix . $entry['foreignTable'],
                $entry['localColumns'],
                $entry['foreignColumns'],
                $entry['options'],
                $entry['fkName'],
            );
        }
    }
}

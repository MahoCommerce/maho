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

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Mage;
use Maho;
use RuntimeException;

final class Collector
{
    /**
     * Walk every active module, load its sql/schema.php closure if present,
     * and let each closure contribute tables to a shared Schema. Then apply
     * the configured table_prefix and the default table options Maho's legacy
     * adapter uses (charset/collation).
     *
     * Module load order respects depends_on, so a later module can
     * $schema->getTable('foo') on a table defined by an earlier one.
     *
     * @return array{0: Schema, 1: list<string>} the final target schema, and the names of modules that contributed
     */
    public static function collect(): array
    {
        $schema = new Schema();
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

        $schema = self::applyTablePrefix($schema);
        self::applyTableDefaults($schema);

        return [$schema, $contributors];
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
     * (matching the convention M2's db_schema.xml uses).
     *
     * DBAL 4.x's Schema::renameTable only updates the legacy _name field and
     * leaves the parsed-identifier API stale, so we can't mutate in place
     * without depending on deprecated accessors. Instead we rebuild a fresh
     * Schema with prefixed Tables and ForeignKeyConstraints, reusing the
     * original columns / indexes / primary keys / options as-is.
     */
    private static function applyTablePrefix(Schema $schema): Schema
    {
        $prefix = (string) Mage::getConfig()->getTablePrefix();
        if ($prefix === '') {
            return $schema;
        }

        $newTables = [];
        foreach ($schema->getTables() as $old) {
            $oldName = $old->getObjectName()->toString();

            $newFks = [];
            foreach ($old->getForeignKeys() as $fk) {
                $newFks[] = $fk->edit()
                    ->setReferencedTableName(
                        OptionallyQualifiedName::unquoted($prefix . $fk->getReferencedTableName()->toString()),
                    )
                    ->create();
            }

            // PrimaryKeyConstraint is rebuilt automatically by Table::_addIndex
            // when it encounters a primary index in the $indexes array, so we
            // pass null to avoid the constructor adding it a second time.
            $newTable = new Table(
                $prefix . $oldName,
                $old->getColumns(),
                $old->getIndexes(),
                $old->getUniqueConstraints(),
                $newFks,
                $old->getOptions(),
            );

            $comment = $old->getComment();
            if ($comment !== null && $comment !== '') {
                $newTable->setComment($comment);
            }

            $newTables[] = $newTable;
        }

        return new Schema($newTables);
    }
}

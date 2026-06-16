<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Db\Schema;

use Doctrine\DBAL\Schema\Name\OptionallyQualifiedName;
use Doctrine\DBAL\Schema\Schema;
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

        $schema = self::rebuildWithPrefix($schema);
        self::applyTableDefaults($schema);

        // The implicit single-column indexes DBAL adds on FK local columns
        // (Table::_addForeignKeyConstraint) are kept. DBAL's Index::isFulfilledBy
        // demands exact column count, so even when a multi-col PK starts with
        // the FK column, DBAL adds a dedicated index. That matches Postgres'
        // needs (no auto-indexing on FKs) and is harmless on MySQL (InnoDB
        // already keeps one when nothing covers). Legacy installs lack these
        // on Postgres and let InnoDB silently add them on MySQL; the
        // declarative schema makes the indexes explicit on every engine,
        // which is the more portable shape.

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
     * Apply the configured table_prefix to table names and FK references.
     * Schema authors declare unprefixed names (matching M2's db_schema.xml
     * convention); the prefix lives in app/etc/local.xml.
     *
     * DBAL 4.x's Schema::renameTable only updates the legacy _name field and
     * leaves the parsed-identifier API stale, so we rebuild via Table::edit()
     * to get fresh OptionallyQualifiedName instances. When the prefix is
     * empty we return the original schema as-is.
     */
    private static function rebuildWithPrefix(Schema $schema): Schema
    {
        $prefix = (string) Mage::getConfig()->getTablePrefix();

        if ($prefix === '') {
            return $schema;
        }

        $newTables = [];
        foreach ($schema->getTables() as $old) {
            $newFks = [];
            foreach ($old->getForeignKeys() as $fk) {
                // getValue() rather than toString(): the latter wraps quoted
                // identifiers in quotes, which would corrupt the concatenation.
                $newFks[] = $fk->edit()
                    ->setReferencedTableName(
                        OptionallyQualifiedName::unquoted($prefix . $fk->getReferencedTableName()->getUnqualifiedName()->getValue()),
                    )
                    ->create();
            }

            $newTables[] = $old->edit()
                ->setName(OptionallyQualifiedName::unquoted($prefix . $old->getObjectName()->getUnqualifiedName()->getValue()))
                ->setForeignKeyConstraints(...$newFks)
                ->create();
        }

        return new Schema($newTables);
    }
}

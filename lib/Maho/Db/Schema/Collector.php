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
use ReflectionClass;
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
        self::stripImplicitForeignKeyIndexes($schema);
        self::applyTableDefaults($schema);

        return [$schema, $contributors];
    }

    /**
     * Drop the single-column indexes DBAL auto-creates on FK local columns.
     *
     * DBAL's Table::_addForeignKeyConstraint generates an implicit index for
     * every FK whose local columns aren't matched by an existing index of the
     * *exact same column count* — Index::isFulfilledBy refuses to credit a
     * 2-col PK starting with `foo` as covering a 1-col FK on `(foo)`. InnoDB
     * already maintains an implicit index per FK and the legacy install
     * scripts relied on that, so the parity baseline doesn't carry these
     * auto-generated indexes.
     *
     * There is no public way to permanently strip them: Table::edit() filters
     * implicitIndexNames out of the TableEditor's index list, but the editor's
     * create() goes through Table::__construct which walks the FKs again and
     * re-adds the implicit indexes. So we reach in via reflection. Apply this
     * AFTER any rebuild (e.g. rebuildWithPrefix), otherwise the construction
     * step would silently re-introduce the indexes we just removed.
     */
    private static function stripImplicitForeignKeyIndexes(Schema $schema): void
    {
        $tableReflection = new ReflectionClass(Table::class);
        $implicitProperty = $tableReflection->getProperty('implicitIndexNames');
        $indexesProperty  = $tableReflection->getProperty('_indexes');

        foreach ($schema->getTables() as $table) {
            $implicit = $implicitProperty->getValue($table);
            if ($implicit === []) {
                continue;
            }
            $indexes = $indexesProperty->getValue($table);
            foreach (array_keys($implicit) as $implicitName) {
                unset($indexes[$implicitName]);
            }
            $indexesProperty->setValue($table, $indexes);
            $implicitProperty->setValue($table, []);
        }
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
                $newFks[] = $fk->edit()
                    ->setReferencedTableName(
                        OptionallyQualifiedName::unquoted($prefix . $fk->getReferencedTableName()->toString()),
                    )
                    ->create();
            }

            $newTables[] = $old->edit()
                ->setName(OptionallyQualifiedName::unquoted($prefix . $old->getObjectName()->toString()))
                ->setForeignKeyConstraints(...$newFks)
                ->create();
        }

        return new Schema($newTables);
    }
}
